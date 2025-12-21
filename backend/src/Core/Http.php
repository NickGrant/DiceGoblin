<?php
declare(strict_types=1);

namespace DiceGoblins\Core;

final class Http
{
  /**
   * @param array<string, string> $headers
   * @return array{status:int, body:string}
   */
  public static function get(string $url, array $headers = []): array
  {
    return self::request('GET', $url, $headers, null);
  }

  /**
   * @param array<string, string> $headers
   * @param array<string, string> $form
   * @return array{status:int, body:string}
   */
  public static function postForm(string $url, array $headers, array $form): array
  {
    $body = http_build_query($form);
    $headers = array_merge([
      'Content-Type' => 'application/x-www-form-urlencoded'
    ], $headers);

    return self::request('POST', $url, $headers, $body);
  }

  /**
   * @param array<string, string> $headers
   * @return array{status:int, body:string}
   */
  private static function request(string $method, string $url, array $headers, ?string $body): array
  {
    $ch = curl_init($url);
    if ($ch === false) {
      throw new \RuntimeException('Failed to init curl');
    }

    $headerLines = [];
    foreach ($headers as $k => $v) {
      $headerLines[] = $k . ': ' . $v;
    }

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headerLines,
      CURLOPT_TIMEOUT => 15,
    ]);

    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $respBody = curl_exec($ch);
    if ($respBody === false) {
      $err = curl_error($ch);
      curl_close($ch);
      throw new \RuntimeException("HTTP request failed: $err");
    }

    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string)$respBody];
  }
}
