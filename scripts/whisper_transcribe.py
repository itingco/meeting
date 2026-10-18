"""Transcribe one audio segment locally; stdout MUST be a single JSON object.

Called by Laravel MeetingAiService using Symfony Process argument array.
No meeting audio is sent to an API. The Whisper model may be downloaded
from Hugging Face the first time this program is run.
"""

import argparse
import json
import os
import sys


def main() -> int:
    parser = argparse.ArgumentParser(description="Local faster-whisper for INGCO Meeting AI")
    parser.add_argument("--audio", required=True)
    parser.add_argument("--model", default="small")
    parser.add_argument("--device", default="cpu")
    parser.add_argument("--compute-type", default="int8")
    parser.add_argument("--language", default="id")
    parser.add_argument("--cpu-threads", type=int, default=6)
    parser.add_argument("--beam-size", type=int, default=3)
    args = parser.parse_args()

    if not os.path.isfile(args.audio):
        print("Audio tidak ditemukan: " + args.audio, file=sys.stderr)
        return 2

    from faster_whisper import WhisperModel

    model = WhisperModel(
        args.model,
        device=args.device,
        compute_type=args.compute_type,
        cpu_threads=max(1, args.cpu_threads),
    )
    segments, _ = model.transcribe(
        args.audio,
        language=args.language or None,
        beam_size=max(1, args.beam_size),
        vad_filter=True,
        condition_on_previous_text=False,
    )

    # Lazy iterator: force decoding and inference before returning to PHP.
    text = " ".join(part.text.strip() for part in segments if part.text.strip()).strip()
    print(json.dumps({"text": text}, ensure_ascii=False), flush=True)
    return 0


if __name__ == "__main__":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
        sys.exit(main())
    except Exception as error:
        print(f"Transkripsi Whisper gagal ({type(error).__name__}): {error}", file=sys.stderr)
        sys.exit(1)
