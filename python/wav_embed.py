"""
StegoLock — WAV Audio Steganography Driver (1-bit LSB)
========================================================
Embeds or extracts binary data into/from PCM WAV files using 1-bit LSB per sample.

Usage:
  python wav_embed.py embed   <carrier_wav> <payload_file> <output_wav>
  python wav_embed.py extract <stego_wav>

All output is a single JSON line on stdout:
  {"success": true,  "data": "<base64_payload>"}
  {"success": false, "error": "<message>", "user_message": "<friendly>"}

Dependencies:
  pip install numpy

Design:
  - Only PCM (uncompressed) WAV supported.
  - 1 bit per sample (LSB of each sample byte).
  - Header (44 bytes) is preserved unchanged.
  - Payload framed as: 4-byte length (big-endian) + raw binary.
  - Safety: capacity check before embedding; abort if payload too large.
"""

import sys
import json
import base64
import os
import struct
import wave
import multiprocessing


def _ok(data=None):
    out = {"success": True}
    if data is not None:
        out["data"] = data
    print(json.dumps(out), flush=True)


def _err(message: str, user_message: str = None):
    out = {"success": False, "error": message}
    if user_message:
        out["user_message"] = user_message
    print(json.dumps(out), flush=True)


def _read_payload(payload_path: str) -> bytes:
    """Read base64-encoded payload from file and return raw bytes."""
    with open(payload_path, 'r', encoding='utf-8') as f:
        b64 = f.read().strip()
    try:
        raw = base64.b64decode(b64, validate=True)
    except Exception:
        raise ValueError("Payload is not valid base64")
    return raw


def _pack_payload(raw: bytes) -> bytes:
    """Frame payload as 4-byte length (BE) + raw bytes."""
    return struct.pack('>I', len(raw)) + raw


def _validate_wav_header(wav: wave.Wave_read, carrier_path: str):
    """Basic WAV validation: PCM, 8/16-bit, 1-2 channels, non-zero frames."""
    comptype = wav.getcomptype()
    if comptype != 'NONE':
        raise ValueError(f"Compressed WAV not supported (compression: {comptype})")

    sampwidth = wav.getsampwidth()
    if sampwidth not in (1, 2):  # 8-bit or 16-bit only
        raise ValueError(f"Unsupported sample width: {sampwidth} bytes (only 8/16-bit PCM supported)")

    channels = wav.getnchannels()
    if channels > 2:
        raise ValueError(f"Too many channels: {channels} (max 2 supported)")

    nframes = wav.getnframes()
    if nframes == 0:
        raise ValueError("WAV file contains no audio frames")


def _capacity_bytes(wav: wave.Wave_read) -> int:
    """Theoretical capacity in bytes (1 bit per sample, all channels)."""
    nframes = wav.getnframes()
    channels = wav.getnchannels()
    total_samples = nframes * channels
    # 1 bit per sample → total_samples bits → /8 bytes
    return total_samples // 8


def _embed_worker(carrier_path: str, raw_payload: bytes, output_path: str, queue):
    """Worker for embedding with timeout support."""
    try:
        import numpy as np

        if not os.path.isfile(carrier_path):
            queue.put({"success": False, "error": f"Carrier not found: {carrier_path}"})
            return

        # Open WAV and validate
        with wave.open(carrier_path, 'rb') as wav:
            _validate_wav_header(wav, carrier_path)
            sampwidth = wav.getsampwidth()
            nframes = wav.getnframes()
            channels = wav.getnchannels()
            framerate = wav.getframerate()
            nbytes = wav.getnframes() * wav.getnchannels() * wav.getsampwidth()

            # Read raw audio data
            frames = wav.readframes(nframes)

        # Convert to numpy array based on sample width
        if sampwidth == 1:
            # 8-bit PCM is unsigned
            dtype = np.uint8
            arr = np.frombuffer(frames, dtype=dtype)
        else:  # 16-bit
            dtype = np.int16
            arr = np.frombuffer(frames, dtype=dtype)

        # Pack payload: length + data
        packed = _pack_payload(raw_payload)
        payload_bits = np.unpackbits(np.frombuffer(packed, dtype=np.uint8))

        required_samples = len(payload_bits)  # 1 bit per sample
        if required_samples > len(arr):
            queue.put({
                "success": False,
                "error": f"Payload too large: need {required_samples} samples, carrier has {len(arr)} samples"
            })
            return

        # Embed: clear LSB and OR with payload bits
        # For 8-bit unsigned: clear LSB by & 0xFE, set with payload bit
        # For 16-bit signed: clear LSB by & 0xFFFE, set with payload bit
        mask = 0xFE if sampwidth == 1 else 0xFFFE
        arr[:required_samples] = (arr[:required_samples] & mask) | payload_bits.astype(arr.dtype)

        # Write output WAV
        out_dir = os.path.dirname(output_path)
        if out_dir:
            os.makedirs(out_dir, exist_ok=True)

        with wave.open(output_path, 'wb') as out_wav:
            out_wav.setnchannels(channels)
            out_wav.setsampwidth(sampwidth)
            out_wav.setframerate(framerate)
            out_wav.writeframes(arr.tobytes())

        queue.put({"success": True, "data": output_path})

    except Exception as exc:
        queue.put({"success": False, "error": str(exc)})


def cmd_embed(carrier_path: str, payload_path: str, output_path: str):
    try:
        if not os.path.isfile(carrier_path):
            _err(f"Carrier file not found: {carrier_path}")
            return

        if not os.path.isfile(payload_path):
            _err(f"Payload file not found: {payload_path}")
            return

        raw_payload = _read_payload(payload_path)

        # Quick capacity check
        with wave.open(carrier_path, 'rb') as wav:
            _validate_wav_header(wav, carrier_path)
            cap = _capacity_bytes(wav)
            if len(raw_payload) > cap:
                _err(f"Payload size ({len(raw_payload)} bytes) exceeds carrier capacity ({cap} bytes)")
                return

        # Use multiprocessing for timeout
        timeout_seconds = int(os.environ.get("STEGO_TIMEOUT_SECONDS", "0"))
        if timeout_seconds > 0:
            ctx = multiprocessing.get_context("spawn")
            queue = ctx.Queue(maxsize=1)
            proc = ctx.Process(target=_embed_worker, args=(carrier_path, raw_payload, output_path, queue))
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
            if not result.get("success"):
                _err(result.get("error", "Embedding failed"))
                return
        else:
            local_q = multiprocessing.Queue(maxsize=1)
            _embed_worker(carrier_path, raw_payload, output_path, local_q)
            result = local_q.get()
            if not result.get("success"):
                _err(result.get("error", "Embedding failed"))
                return

        _ok(output_path)

    except Exception as exc:
        _err(str(exc))


def _extract_worker(stego_path: str, queue):
    """Worker for extraction with timeout support."""
    try:
        import numpy as np

        if not os.path.isfile(stego_path):
            queue.put({"success": False, "error": f"Stego file not found: {stego_path}"})
            return

        with wave.open(stego_path, 'rb') as wav:
            _validate_wav_header(wav, stego_path)
            sampwidth = wav.getsampwidth()
            nframes = wav.getnframes()
            channels = wav.getnchannels()
            frames = wav.readframes(nframes)

        if sampwidth == 1:
            arr = np.frombuffer(frames, dtype=np.uint8)
        else:
            arr = np.frombuffer(frames, dtype=np.int16)

        # Extract bits from LSB of each sample
        # We need to recover the length first (32 bits)
        total_bits_needed = 32  # length header
        if len(arr) < total_bits_needed:
            queue.put({"success": False, "error": "Carrier too small to contain header"})
            return

        length_bits = (arr[:32] & 1).astype(np.uint8)
        length_bytes = np.packbits(length_bits)
        payload_len = int.from_bytes(length_bytes.tobytes(), 'big')

        if payload_len < 0 or payload_len > 10 * 1024 * 1024:
            queue.put({"success": False, "error": f"Invalid payload length: {payload_len}"})
            return

        # Extract payload bits
        total_payload_bits = payload_len * 8
        total_needed = 32 + total_payload_bits
        if len(arr) < total_needed:
            queue.put({"success": False, "error": "Carrier does not contain enough data for declared payload length"})
            return

        payload_bits = (arr[32:total_needed] & 1).astype(np.uint8)
        payload_bytes = np.packbits(payload_bits).tobytes()

        b64_payload = base64.b64encode(payload_bytes).decode('utf-8')
        queue.put({"success": True, "data": b64_payload})

    except Exception as exc:
        queue.put({"success": False, "error": str(exc)})


def cmd_extract(stego_path: str):
    try:
        if not os.path.isfile(stego_path):
            _err(f"Stego file not found: {stego_path}")
            return

        timeout_seconds = int(os.environ.get("STEGO_TIMEOUT_SECONDS", "0"))
        if timeout_seconds > 0:
            ctx = multiprocessing.get_context("spawn")
            queue = ctx.Queue(maxsize=1)
            proc = ctx.Process(target=_extract_worker, args=(stego_path, queue))
            proc.start()
            proc.join(timeout_seconds)
            if proc.is_alive():
                proc.terminate()
                proc.join()
                _err(f"Extract timed out after {timeout_seconds} seconds")
                return
            if queue.empty():
                _err("Extraction worker exited unexpectedly")
                return
            result = queue.get()
            if not result.get("success"):
                _err(result.get("error", "Extraction failed"))
                return
        else:
            local_q = multiprocessing.Queue(maxsize=1)
            _extract_worker(stego_path, local_q)
            result = local_q.get()
            if not result.get("success"):
                _err(result.get("error", "Extraction failed"))
                return

        _ok(result["data"])

    except Exception as exc:
        _err(str(exc))


def cmd_capacity(carrier_path: str):
    """Report capacity in bytes (with 0.95 safety factor)."""
    try:
        if not os.path.isfile(carrier_path):
            _err(f"Carrier file not found: {carrier_path}")
            return

        with wave.open(carrier_path, 'rb') as wav:
            _validate_wav_header(wav, carrier_path)
            cap = _capacity_bytes(wav)
            # Apply 0.95 safety factor
            safe_cap = int(cap * 0.95)

        _ok(safe_cap)

    except Exception as exc:
        _err(str(exc))


# ---------------------------------------------------------------------------
# MAIN
# ---------------------------------------------------------------------------

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "Usage: wav_embed.py <embed|extract|capacity> [args...]"}), flush=True)
        sys.exit(1)

    cmd = sys.argv[1].lower()

    if cmd == 'embed':
        if len(sys.argv) != 5:
            print(json.dumps({"success": False, "error": "Usage: wav_embed.py embed <carrier> <payload_file> <output>"}), flush=True)
            sys.exit(1)
        cmd_embed(sys.argv[2], sys.argv[3], sys.argv[4])

    elif cmd == 'extract':
        if len(sys.argv) != 3:
            print(json.dumps({"success": False, "error": "Usage: wav_embed.py extract <stego>"}), flush=True)
            sys.exit(1)
        cmd_extract(sys.argv[2])

    elif cmd == 'capacity':
        if len(sys.argv) != 3:
            print(json.dumps({"success": False, "error": "Usage: wav_embed.py capacity <carrier>"}), flush=True)
            sys.exit(1)
        cmd_capacity(sys.argv[2])

    else:
        print(json.dumps({"success": False, "error": f"Unknown command: {cmd}"}), flush=True)
        sys.exit(1)
