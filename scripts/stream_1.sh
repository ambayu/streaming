#!/bin/bash

LOGFILE="E:\Project\laravel\streaming\storage\logs/stream_1.log"
YOUTUBE_KEY="test_youtube_key"
VIDEOS=("E:\Project\laravel\streaming\storage\framework/testing/disks/public\video1.mp4")

mkdir -p "$(dirname "$LOGFILE")"
chown www-data:www-data "$(dirname "$LOGFILE")"
chmod 755 "$(dirname "$LOGFILE")"

while true; do
  for f in "${VIDEOS[@]}"; do
    echo "$(date): Streaming $f" >> "$LOGFILE"

    # run with nice/ionice, single thread, minimal logging
    nice -n 19 ionice -c2 -n7 \
      ffmpeg -re -i "$f" -threads 1 \
             -c:v copy -c:a copy -loglevel error \
             -flvflags no_duration_filesize -f flv "rtmps://a.rtmps.youtube.com/live2/$YOUTUBE_KEY" \
      >>"$LOGFILE" 2>&1

    if [ $? -ne 0 ]; then
      echo "$(date): ERROR streaming $f" >> "$LOGFILE"
    else
      echo "$(date): Finished $f" >> "$LOGFILE"
    fi
  done
  echo "$(date): Menunggu 10 detik sebelum loop berikutnya..." >> "$LOGFILE"
  sleep 10
done