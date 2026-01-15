<?php
// backend/src/config.php

return [

  /* ============================
      🔐 CONFIG TOKEN JWT
     ============================ */

  'jwt_secret' => 'fadb12b98fb2a5b493da9ac23e8919944f53f0a9b062725afafec1fd7aba8d40',
  'jwt_iss'    => 'ecoride',
  'jwt_aud'    => 'ecoride-front',

  /* ============================
      🌍 CONFIG CORS
     ============================ */

  'cors_allowed' => [
    'http://127.0.0.1:5500',
    'http://localhost:5500'
  ],

  /* ============================
      ⏱️ Durées JWT
     ============================ */

  'access_ttl'  => 15 * 60,          // 15 minutes
  'refresh_ttl' => 14 * 24 * 3600,   // 14 jours

  /* ============================
      🍪 Cookies
     ============================ */

  'cookie_secure'   => false,
  'cookie_samesite' => 'Lax',

  /* ============================
      🛢️ CONFIG 
     ============================ */

  'db' => [
      'host'    => '127.0.0.1',
      'port'    => 3306,
      'name'    => 'ecoride_db',
      'charset' => 'utf8mb4',          
      'user'    => 'root',
      'pass'    => ''                // XAMPP = vide
  ],
];
