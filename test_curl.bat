@echo off
echo Testing connection to Yupoo...

set YUPOO_URL=https://297228164.x.yupoo.com
set OUTPUT_FILE=curl_output.txt

curl -v -L -A "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36" ^
  -H "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8" ^
  -H "Accept-Language: en-US,en;q=0.5" ^
  -H "Connection: keep-alive" ^
  -H "Upgrade-Insecure-Requests: 1" ^
  --insecure ^
  --connect-timeout 30 ^
  --max-time 60 ^
  --output %OUTPUT_FILE% ^
  %YUPOO_URL%

echo.
echo ====================
echo cURL command completed.

echo.
echo Response saved to %OUTPUT_FILE%
echo.

echo First 10 lines of response:
echo ====================
type %OUTPUT_FILE% | findstr /n "^" | findstr /b /r "^[0-9][0-9]*:" | findstr /b /r "^[1-9]: ^10:"

echo.
echo ====================
echo Test complete. Check %OUTPUT_FILE% for full response.
