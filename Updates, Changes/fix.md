Yes. The bottleneck is almost always in one of three places — let's identify which first, then fix each.

---

## Where the time actually goes

```
File read → LSB bit manipulation → PSNR measurement → File write
              ↑
         this is usually 80% of the time for large files
```

For a 5MB payload embedded into a large PNG, the LSB loop in pure Python can take 10–30 seconds because it's iterating pixel by pixel in a Python `for` loop. That's the primary target.

---

## Fix 1 — Replace the pixel loop with NumPy (biggest gain)

This is the single most impactful change. A pure Python LSB loop on a large image is orders of magnitude slower than the same operation vectorized with NumPy.

**Before (typical slow implementation):**
```python
def embed(image_path, data):
    img = Image.open(image_path)
    pixels = list(img.getdata())
    bits = ''.join(format(byte, '08b') for byte in data)
    
    idx = 0
    new_pixels = []
    for pixel in pixels:           # ← pure Python loop, slow
        new_pixel = list(pixel)
        for i in range(3):
            if idx < len(bits):
                new_pixel[i] = (new_pixel[i] & ~1) | int(bits[idx])
                idx += 1
        new_pixels.append(tuple(new_pixel))
    
    img.putdata(new_pixels)
    img.save(image_path)
```

**After (NumPy vectorized):**
```python
import numpy as np
from PIL import Image

def embed(image_path: str, data: bytes, output_path: str) -> None:
    img = Image.open(image_path).convert('RGB')
    arr = np.array(img, dtype=np.uint8)

    # Flatten to 1D array of channel values
    flat = arr.flatten()

    # Convert data to bits as a NumPy array in one shot
    bits = np.unpackbits(np.frombuffer(data, dtype=np.uint8))

    if len(bits) > len(flat):
        raise ValueError(f"The message you want to hide is too long: {len(data)}")

    # Clear LSB of target pixels, then OR in the message bits — no Python loop
    flat[:len(bits)] = (flat[:len(bits)] & 0xFE) | bits

    # Reshape back and save
    result = flat.reshape(arr.shape)
    Image.fromarray(result, 'RGB').save(output_path, compress_level=1)
```

**Decoding equivalent:**
```python
def decode(image_path: str, length_bytes: int) -> bytes:
    img = Image.open(image_path).convert('RGB')
    arr = np.array(img, dtype=np.uint8)

    flat = arr.flatten()
    bits = (flat[:length_bytes * 8] & 1).astype(np.uint8)

    return np.packbits(bits).tobytes()
```

**Expected speedup: 10x–50x** on large images. This alone may bring a 30-second encode down to under 2 seconds.

---

## Fix 2 — Use 2 LSBs instead of 1 (halves iterations, minor quality tradeoff)

Instead of hiding 1 bit per channel, use the 2 least significant bits. This halves the number of pixels needed and doubles throughput, with a small PSNR cost (usually still well above 40 dB for most images).

```python
BITS_PER_CHANNEL = 2
MASK_CLEAR = 0xFF ^ ((1 << BITS_PER_CHANNEL) - 1)  # 0xFC
MASK_BITS  = (1 << BITS_PER_CHANNEL) - 1            # 0x03

def embed_2lsb(image_path: str, data: bytes, output_path: str) -> None:
    img = Image.open(image_path).convert('RGB')
    arr = np.array(img, dtype=np.uint8)
    flat = arr.flatten()

    # Pack data into 2-bit groups
    bits = np.unpackbits(np.frombuffer(data, dtype=np.uint8))

    # Pad to multiple of 2
    pad = (2 - len(bits) % 2) % 2
    bits = np.pad(bits, (0, pad))
    two_bit_groups = bits.reshape(-1, 2)
    packed = (two_bit_groups[:, 0] << 1) | two_bit_groups[:, 1]

    n = len(packed)
    if n > len(flat):
        raise ValueError(f"The message you want to hide is too long: {len(data)}")

    flat[:n] = (flat[:n] & MASK_CLEAR) | packed
    Image.fromarray(flat.reshape(arr.shape), 'RGB').save(output_path, compress_level=1)
```

> Measure PSNR after switching to confirm it stays ≥ 40 dB. For most natural images it will — synthetic/low-detail images are more sensitive.

---

## Fix 3 — Save with low PNG compression

PNG compression doesn't affect image quality but it does affect write time. By default PIL uses compression level 6. Setting it to 1 makes writes significantly faster with a slightly larger file size — acceptable since the file goes to R2/B2 anyway.

```python
# Slow (default)
img.save(output_path)

# Fast
img.save(output_path, format='PNG', compress_level=1)

# Fastest (no compression — largest file, only use if storage isn't a concern)
img.save(output_path, format='PNG', compress_level=0)
```

---

## Fix 4 — Run segments in parallel (Laravel side)

If a document produces multiple segments, you're currently embedding them sequentially. Each segment is independent — they can all run at the same time.

```php
// In StegoDocumentService::encode()
// Instead of:
foreach ($segments as $index => $chunk) {
    $outputPath = $this->embedder->embed(...);
    // ...
}

// Do this — dispatch all segment jobs concurrently
$jobs = [];
foreach ($segments as $index => $chunk) {
    $jobs[] = new EmbedSegmentJob(
        stegoDocumentId: $stegoDoc->id,
        carrierId:       $carriers[$index]->id,
        chunk:           $chunk,
        segmentIndex:    $index,
    );
}

Bus::batch($jobs)
    ->then(function (Batch $batch) use ($stegoDoc) {
        $stegoDoc->update(['status' => 'ready']);
    })
    ->catch(function (Batch $batch, Throwable $e) use ($stegoDoc) {
        $stegoDoc->update(['status' => 'failed', 'failed_reason' => $e->getMessage()]);
    })
    ->dispatch();
```

```php
// app/Jobs/EmbedSegmentJob.php
class EmbedSegmentJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int    $stegoDocumentId,
        public readonly int    $carrierId,
        public readonly string $chunk,
        public readonly int    $segmentIndex,
    ) {}

    public function handle(EmbedService $embedder, StegoStorageService $storage): void
    {
        $carrier = StegCarrier::findOrFail($this->carrierId);

        $embeddedContents = $embedder->embed(
            $carrier->file_path,
            $this->chunk,
            $carrier->mime_type
        );

        $r2Key = sprintf(
            'stego/output/%d/segment-%d.%s',
            $this->stegoDocumentId,
            $this->segmentIndex,
            $carrier->file_type
        );

        $storage->put($r2Key, $embeddedContents);

        StegSegment::create([
            'stego_document_id' => $this->stegoDocumentId,
            'stego_carrier_id'  => $this->carrierId,
            'segment_index'     => $this->segmentIndex,
            'encrypted_chunk'   => $this->chunk,
            'chunk_hash'        => hash('sha256', $this->chunk),
            's3_key'            => $r2Key,
        ]);
    }
}
```

With 5 segments on 5 queue workers running at once, a 5× speedup is realistic.

---

## Fix 5 — Cache the image array between PSNR and embed

If your flow opens the same carrier image twice — once for PSNR and once for embedding — you're doing double I/O and double decode. Combine them into a single Python script call:

```python
# scripts/stego/embed_and_measure.py
import sys, json
import numpy as np
from PIL import Image
from skimage.metrics import peak_signal_noise_ratio

def run(carrier_path, data_hex, output_path):
    data = bytes.fromhex(data_hex)
    
    original = np.array(Image.open(carrier_path).convert('RGB'), dtype=np.uint8)
    arr = original.copy()
    flat = arr.flatten()

    bits = np.unpackbits(np.frombuffer(data, dtype=np.uint8))

    if len(bits) > len(flat):
        raise ValueError(f"The message you want to hide is too long: {len(data)}")

    flat[:len(bits)] = (flat[:len(bits)] & 0xFE) | bits
    result = flat.reshape(arr.shape)

    # Measure PSNR against original in the same call — no second file open
    psnr = peak_signal_noise_ratio(original, result, data_range=255)

    Image.fromarray(result, 'RGB').save(output_path, compress_level=1)

    print(json.dumps({'psnr': round(psnr, 4), 'output': output_path}))

if __name__ == '__main__':
    run(sys.argv[1], sys.argv[2], sys.argv[3])
```

---

## Expected combined improvement

| Fix | Effort | Speedup |
|---|---|---|
| NumPy vectorization | Low | 10×–50× |
| PNG compress_level=1 | Trivial | 2×–3× on write |
| 2 LSBs per channel | Low | ~2× |
| Parallel segment jobs | Medium | N× (one per worker) |
| Single script for embed+PSNR | Low | Eliminates duplicate I/O |

For a 2–5 MB payload, the NumPy fix alone should bring encode time from 20–30 seconds down to 1–3 seconds. Add parallel jobs and the wall-clock time for a multi-segment document becomes roughly the time of the slowest single segment.