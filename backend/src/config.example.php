<?php
return [

  'jwt_secret' => 'CHANGE_ME',

  'jwt_iss' => 'ecoride',
  'jwt_aud' => 'ecoride-front',

  'cors_allowed' => [
    'http://127.0.0.1:5500',
    'http://localhost:5500'
  ],

  'access_ttl'  => 15 * 60,
  'refresh_ttl' => 14 * 24 * 3600,

  'cookie_secure'   => false,
  'cookie_samesite' => 'Lax',

  'db' => [
      'host'    => '127.0.0.1',
      'port'    => 3306,
      'name'    => 'ecoride_db',
      'charset' => 'utf8mb4',
      'user'    => 'root',
      'pass'    => ''
  ],
];
