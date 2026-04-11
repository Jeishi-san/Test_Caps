"""
StegoLock — Python LSB Steganography Driver
============================================
Called by Laravel's StegoService via symfony/process.

Usage:
  python stego_lsb.py embed    <carrier_path> <payload_file> <output_path>
  python stego_lsb.py extract  <stego_path>
  python stego_lsb.py capacity <image_path>

All output is a single JSON line on stdout:
  {"success": true,  "data": "<value>"}
  {"success": false, "error": "<message>"}

Binary payloads are base64-encoded by PHP before embedding.
This driver uses a vectorized NumPy implementation with 2 LSBs/channel.

Dependencies:
    pip install numpy Pillow

Optional legacy extraction fallback (old stegano-based images):
    pip install stegano
"""

import sys
import json
import base64
import os
import multiprocessing


BITS_PER_CHANNEL = 2
LSB_MASK = (1 << BITS_PER_CHANNEL) - 1
CLEAR_MASK = 0xFF ^ LSB_MASK
HEADER_MAGIC = b"STG2"
HEADER_SIZE_BYTES = 8  # 4-byte magic + 4-byte payload length


def _ok(data) -> None:
    print(json.dumps({"success": True, "data": data}), flush=True)


def _err(message: str) -> None:
    print(json.dumps({"success": False, "error": message}), flush=True)


def _internal_timeout_seconds() -> int:
    """
    Read optional inner timeout configured by the PHP caller.
   
    This timeout is intentionally lower than Process::timeout so Python can
    stop cleanly before the outer process layer force-terminates it.
    """
    raw = os.environ.get("STEGO_TIMEOUT_SECONDS", "0").strip()
    try:
        seconds = int(raw)
    except ValueError:
        return 0
    return max(0, seconds)


def _pack_payload(b64_payload: str) -> bytes:
    payload_bytes = b64_payload.encode("utf-8")
    return HEADER_MAGIC + len(payload_bytes).to_bytes(4, "big") + payload_bytes


def _save_image_fast(image_array, output_path: str) -> None:
    from PIL import Image

    output_image = Image.fromarray(image_array, "RGB")
    ext = os.path.splitext(output_path)[1].lower()

    if ext == ".png":
        output_image.save(output_path, format="PNG", compress_level=1)
    else:
        output_image.save(output_path)


def _embed_worker(carrier_path: str, b64_payload: str, output_path: str, queue) -> None:
    """Run vectorized 2-LSB embedding in a child process for timeout enforcement."""
    try:
        import numpy as np
        from PIL import Image

        img = Image.open(carrier_path).convert("RGB")
        arr = np.array(img, dtype=np.uint8)
        flat = arr.reshape(-1)

        packed_payload = _pack_payload(b64_payload)

        payload_bits = np.unpackbits(np.frombuffer(packed_payload, dtype=np.uint8))
        pad = (-len(payload_bits)) % BITS_PER_CHANNEL
        if pad:
            payload_bits = np.pad(payload_bits, (0, pad), mode="constant")

        grouped = payload_bits.reshape(-1, BITS_PER_CHANNEL)
        encoded_values = (grouped[:, 0] << 1) | grouped[:, 1]
        required_channels = len(encoded_values)

        if required_channels > len(flat):
            queue.put({
                "success": False,
                "error": f"The message you want to hide is too long: {len(b64_payload)}",
            })
            return

        flat[:required_channels] = (flat[:required_channels] & CLEAR_MASK) | encoded_values.astype(np.uint8)

        out_dir = os.path.dirname(output_path)
        if out_dir:
            os.makedirs(out_dir, exist_ok=True)

        _save_image_fast(flat.reshape(arr.shape), output_path)
        queue.put({"success": True})
    except Exception as exc:
        queue.put({"success": False, "error": str(exc)})


def _extract_bytes_from_flat(flat, start_channel: int, byte_count: int):
    import numpy as np

    if byte_count <= 0:
        return b""

    bit_count = byte_count * 8
    channels_needed = (bit_count + BITS_PER_CHANNEL - 1) // BITS_PER_CHANNEL

    end_channel = start_channel + channels_needed
    if end_channel > len(flat):
        raise ValueError("Carrier does not contain enough embedded data")

    values = (flat[start_channel:end_channel] & LSB_MASK).astype(np.uint8)

    bits = np.empty(channels_needed * BITS_PER_CHANNEL, dtype=np.uint8)
    bits[0::2] = (values >> 1) & 1
    bits[1::2] = values & 1
    bits = bits[:bit_count]

    return np.packbits(bits).tobytes()


def _extract_2lsb_payload(stego_path: str) -> str:
    import numpy as np
    from PIL import Image

    flat = np.array(Image.open(stego_path).convert("RGB"), dtype=np.uint8).reshape(-1)

    header = _extract_bytes_from_flat(flat, 0, HEADER_SIZE_BYTES)
    magic = header[:4]

    if magic != HEADER_MAGIC:
        raise ValueError("Carrier does not contain STG2 payload header")

    payload_len = int.from_bytes(header[4:8], "big")
    if payload_len < 0:
        raise ValueError("Invalid embedded payload length")

    data_start_channel = (HEADER_SIZE_BYTES * 8 + BITS_PER_CHANNEL - 1) // BITS_PER_CHANNEL
    payload_bytes = _extract_bytes_from_flat(flat, data_start_channel, payload_len)

    return payload_bytes.decode("utf-8")


def _extract_legacy_payload(stego_path: str):
    """Fallback for older images created with the prior stegano-based format."""
    try:
        from stegano import lsb
    except Exception:
        return None

    message = lsb.reveal(stego_path)
    if not message:
        return None

    return message


# ---------------------------------------------------------------------------
# EMBED
# ---------------------------------------------------------------------------

def cmd_embed(carrier_path: str, payload_path: str, output_path: str) -> None:
    """
    Hide base64-encoded binary data into a carrier image using LSB.

    The stegano library only accepts string payloads, so the binary
    encrypted chunk from PHP is pre-encoded as base64 before being passed
    to this script and post-decoded on extraction.
    
    Payload is read from a temporary file instead of command-line argument
    to avoid Windows command line length limits.
    """
    try:
        if not os.path.isfile(carrier_path):
            _err(f"Carrier file not found: {carrier_path}")
            return

        if not os.path.isfile(payload_path):
            _err(f"Payload file not found: {payload_path}")
            return

        # Read payload from file
        with open(payload_path, 'r', encoding='utf-8') as f:
            b64_payload = f.read().strip()

        # Validate base64 input
        try:
            base64.b64decode(b64_payload, validate=True)
        except Exception:
            _err("Payload is not valid base64")
            return

        timeout_seconds = _internal_timeout_seconds()

        if timeout_seconds > 0:
            ctx = multiprocessing.get_context("spawn")
            queue = ctx.Queue(maxsize=1)
            proc = ctx.Process(
                target=_embed_worker,
                args=(carrier_path, b64_payload, output_path, queue),
            )
            proc.start()
            proc.join(timeout_seconds)

            if proc.is_alive():
                proc.terminate()
                proc.join()
                _err(f"Embed timed out after {timeout_seconds} seconds")
                return

            if queue.empty():
                _err("Embedding worker exited unexpectedly")
                return

            result = queue.get()
            if not result.get("success", False):
                _err(result.get("error", "Embedding worker failed"))
                return
        else:
            local_queue = multiprocessing.Queue(maxsize=1)
            _embed_worker(carrier_path, b64_payload, output_path, local_queue)
            result = local_queue.get()
            if not result.get("success", False):
                _err(result.get("error", "Embedding worker failed"))
                return

        _ok(output_path)

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# EXTRACT
# ---------------------------------------------------------------------------

def cmd_extract(stego_path: str) -> None:
    """
    Reveal the hidden base64-encoded payload from a stego image.
    Returns the raw base64 string — PHP decodes it back to binary.
    """
    try:
        if not os.path.isfile(stego_path):
            _err(f"Stego image not found: {stego_path}")
            return

        try:
            message = _extract_2lsb_payload(stego_path)
        except Exception as vectorized_exc:
            message = _extract_legacy_payload(stego_path)
            if message is None:
                _err(str(vectorized_exc))
                return

        # Validate we got valid base64 back (sanity check)
        try:
            base64.b64decode(message, validate=True)
        except Exception:
            _err("Extracted payload is not valid base64. Image may be corrupt or encoded with a different tool.")
            return

        _ok(message)

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# CAPACITY
# ---------------------------------------------------------------------------

def cmd_capacity(image_path: str) -> None:
    """
    Calculate the maximum payload capacity (in bytes) of an image.

    Formula mirrors the PHP StegoService:
            capacity = (width * height * 3 channels * 2 bits/channel) / 8 bits - 8 bytes header
    Then divided by 4/3 to account for base64 overhead (binary → base64 inflates by ~33%).
    """
    try:
        from PIL import Image

        if not os.path.isfile(image_path):
            _err(f"Image not found: {image_path}")
            return

        with Image.open(image_path) as img:
            width, height = img.size

        # Raw LSB capacity in bytes
        raw_capacity = (width * height * 3 * BITS_PER_CHANNEL) // 8 - HEADER_SIZE_BYTES

        # Adjust for base64 overhead: base64 encoding inflates size by 4/3
        # So usable binary bytes = raw_capacity * 3 / 4
        usable_capacity = int(raw_capacity * 3 / 4)

        _ok(max(0, usable_capacity))

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# PSNR  (W2-T07 / W2-T12)
# ---------------------------------------------------------------------------

def cmd_psnr(original_path: str, stego_path: str) -> None:
    """
    Calculate the Peak Signal-to-Noise Ratio between the original carrier and
    the stego image to quantify the visual quality impact of LSB embedding.

    PSNR >= 40 dB is the accepted threshold for imperceptible modifications.
    Uses OpenCV's cv2.PSNR() which computes 10 * log10(MAX_I^2 / MSE).

    Returns JSON:
      { "psnr": <float>, "threshold_40db": <bool>, "quality": "good"|"poor" }
    """
    try:
        import cv2

        for label, path in [("Original", original_path), ("Stego", stego_path)]:
            if not os.path.isfile(path):
                _err(f"{label} image not found: {path}")
                return

        original = cv2.imread(original_path)
        stego    = cv2.imread(stego_path)

        if original is None:
            _err(f"Could not decode original image: {original_path}")
            return
        if stego is None:
            _err(f"Could not decode stego image: {stego_path}")
            return

        # Images may differ in size when a JPEG carrier was embedded and
        # saved as PNG (format conversion can alter reported dimensions).
        if original.shape != stego.shape:
            stego = cv2.resize(stego, (original.shape[1], original.shape[0]))

        psnr_value = cv2.PSNR(original, stego)

        _ok({
            "psnr":           round(psnr_value, 4),
            "threshold_40db": psnr_value >= 40.0,
            "quality":        "good" if psnr_value >= 40.0 else "poor",
        })

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# Entry Point
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    if len(sys.argv) < 2:
        _err("Usage: stego_lsb.py <embed|extract|capacity> [args...]")
        sys.exit(1)

    command = sys.argv[1].lower()

    if command == "embed":
        if len(sys.argv) != 5:
            _err("Usage: stego_lsb.py embed <carrier_path> <payload_file> <output_path>")
            sys.exit(1)
        cmd_embed(sys.argv[2], sys.argv[3], sys.argv[4])

    elif command == "extract":
        if len(sys.argv) != 3:
            _err("Usage: stego_lsb.py extract <stego_path>")
            sys.exit(1)
        cmd_extract(sys.argv[2])

    elif command == "capacity":
        if len(sys.argv) != 3:
            _err("Usage: stego_lsb.py capacity <image_path>")
            sys.exit(1)
        cmd_capacity(sys.argv[2])

    elif command == "psnr":
        if len(sys.argv) != 4:
            _err("Usage: stego_lsb.py psnr <original_path> <stego_path>")
            sys.exit(1)
        cmd_psnr(sys.argv[2], sys.argv[3])

    else:
        _err(f"Unknown command: {command}. Use embed, extract, capacity, or psnr.")
        sys.exit(1)
