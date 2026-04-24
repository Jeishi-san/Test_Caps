"""
StegoLock — WAV Validation Utility
====================================
Validates a WAV file for steganographic use.

Usage:
  python wav_validator.py <wav_file_path>

Output (JSON on stdout):
  {
    "valid": true|false,
    "capacity_bytes": <int>,   // safe capacity with 0.95 factor
    "sample_width": <int>,     // bytes (1 or 2)
    "channels": <int>,
    "nframes": <int>,
    "reason": "<error message>" // only if valid=false
  }

Validation rules:
  - Must be a valid RIFF/WAVE file
  - Compression type must be 'NONE' (PCM only)
  - Sample width must be 8-bit (1) or 16-bit (2)
  - Channels must be 1 or 2
  - Must have at least 1 audio frame
  - Capacity = ((filesize - 44) * 8 / 8) * 0.95 = (filesize - 44) * 0.95
"""

import sys
import json
import wave
import os


def validate_wav(path: str) -> dict:
    try:
        if not os.path.isfile(path):
            return {'valid': False, 'reason': f'File not found: {path}'}

        with wave.open(path, 'rb') as wav:
            # Check RIFF/WAVE magic is implicit in wave module success

            comptype = wav.getcomptype()
            if comptype != 'NONE':
                return {'valid': False, 'reason': f'Non-PCM WAV not supported (compression: {comptype})'}

            sampwidth = wav.getsampwidth()
            if sampwidth not in (1, 2):
                return {'valid': False, 'reason': f'Unsupported sample width: {sampwidth} bytes (only 8/16-bit PCM supported)'}

            channels = wav.getnchannels()
            if channels > 2:
                return {'valid': False, 'reason': f'Too many channels: {channels} (max 2 supported)'}
            if channels == 0:
                return {'valid': False, 'reason': 'Zero channels'}

            nframes = wav.getnframes()
            if nframes == 0:
                return {'valid': False, 'reason': 'WAV file contains no audio frames'}

            # Calculate capacity: 1 bit per sample (per frame per channel)
            # Total samples = nframes * channels
            # Bytes = floor(total_samples / 8)
            # Apply 0.95 safety factor
            total_samples = nframes * channels
            capacity_bytes = total_samples // 8
            safe_capacity = int(capacity_bytes * 0.95)

            return {
                'valid': True,
                'capacity_bytes': safe_capacity,
                'sample_width': sampwidth,
                'channels': channels,
                'nframes': nframes,
                'raw_capacity_bytes': capacity_bytes,
            }

    except wave.Error as e:
        return {'valid': False, 'reason': f'Invalid WAV structure: {e}'}
    except Exception as e:
        return {'valid': False, 'reason': f'Cannot read file: {e}'}


if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'valid': False, 'reason': 'Usage: python wav_validator.py <wav_file>'}))
        sys.exit(1)

    result = validate_wav(sys.argv[1])
    print(json.dumps(result))
