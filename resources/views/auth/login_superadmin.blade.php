<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: #0F0F0F;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .card {
      background: #1A1A1A;
      border: 1px solid #2A2A2A;
      border-radius: 16px;
      padding: 2.5rem;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 24px 64px rgba(0,0,0,.5);
    }
    .icon-wrap {
      width: 56px; height: 56px;
      border-radius: 14px;
      background: #E53935;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.5rem;
    }
    h1 {
      font-size: 1.3rem; font-weight: 700;
      color: #fff; text-align: center;
      margin-bottom: .375rem;
    }
    .subtitle {
      font-size: .8rem; color: #555;
      text-align: center; margin-bottom: 2rem;
    }
    .alert {
      background: #2A1515; border: 1px solid #5A2020;
      border-radius: 8px; padding: .75rem 1rem;
      font-size: .82rem; color: #FF6B6B;
      margin-bottom: 1.25rem;
      display: flex; align-items: center; gap: .5rem;
    }
    label {
      display: block; font-size: .78rem;
      font-weight: 600; color: #777;
      margin-bottom: .35rem;
      text-transform: uppercase; letter-spacing: .05em;
    }
    .input-wrap {
      position: relative; margin-bottom: 1rem;
    }
    input {
      width: 100%;
      background: #111; border: 1.5px solid #2A2A2A;
      border-radius: 8px; padding: .7rem 1rem;
      color: #fff; font-family: inherit; font-size: .9rem;
      transition: border-color .15s;
      outline: none;
    }
    input:focus { border-color: #E53935; }
    input::placeholder { color: #444; }
    .toggle-btn {
      position: absolute; right: .75rem; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; color: #555;
      cursor: pointer; font-size: .9rem;
    }
    button[type=submit] {
      width: 100%; background: #E53935;
      border: none; border-radius: 8px;
      padding: .8rem; color: #fff;
      font-family: inherit; font-size: .9rem;
      font-weight: 700; cursor: pointer;
      transition: background .15s;
      margin-top: .5rem;
    }
    button[type=submit]:hover { background: #B71C1C; }
    .back-link {
      display: block; text-align: center;
      margin-top: 1.25rem; font-size: .78rem; color: #444;
      text-decoration: none; transition: color .15s;
    }
    .back-link:hover { color: #E53935; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon-wrap">
      <i class="bi bi-shield-lock-fill" style="font-size:1.4rem;color:#fff"></i>
    </div>
    <h1>Admin Portal</h1>
    <p class="subtitle">Authorized personnel only</p>

    @if(session('error'))
      <div class="alert">
        <i class="bi bi-exclamation-circle-fill"></i>
        {{ session('error') }}
      </div>
    @endif
    @if(session('msg'))
      <div class="alert" style="background:#0D2A1A;border-color:#1A5A2A;color:#4CAF50">
        <i class="bi bi-check-circle-fill"></i>
        {{ session('msg') }}
      </div>
    @endif

    <form action="{{ route('superadmin.login.post') }}" method="POST" novalidate>
      @csrf
      <div>
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="{{ old('username') }}"
               placeholder="Enter username"
               required autofocus autocomplete="username">
      </div>
      <div>
        <label for="password">Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password"
                 placeholder="Enter password"
                 required autocomplete="current-password">
          <button type="button" class="toggle-btn" onclick="togglePwd()">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>
      <button type="submit">Sign In</button>
    </form>

    <a href="{{ route('platform.home') }}" class="back-link">
      &larr; Back to Platform
    </a>
  </div>

  <script>
  function togglePwd() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }
  </script>
</body>
</html>
