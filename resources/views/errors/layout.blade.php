<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Error') — Cake Shop</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎂</text></svg>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

    :root {
      --primary:       #7B3A0F;
      --primary-dark:  #561f06;
      --primary-light: #d4956b;
      --primary-bg:    #fdf5f0;
      --cream:         #FFF8F8;
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--primary-bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
      padding: 1.5rem;
    }

    .error-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 8px 40px rgba(123,58,15,.12);
      padding: 3rem 2.5rem;
      max-width: 520px;
      width: 100%;
      text-align: center;
    }

    .error-icon-wrap {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.25rem;
    }

    .error-icon-wrap.danger  { background: #fff1f0; color: #c0392b; }
    .error-icon-wrap.warning { background: #fff8e1; color: #e67e22; }
    .error-icon-wrap.info    { background: #e8f4fd; color: #2980b9; }

    .error-icon-wrap i { font-size: 2.4rem; }

    .error-code {
      font-family: 'Playfair Display', serif;
      font-size: 5rem;
      font-weight: 700;
      line-height: 1;
      color: var(--primary);
      letter-spacing: -2px;
      margin-bottom: .25rem;
    }

    .error-title {
      font-size: 1.35rem;
      font-weight: 700;
      color: #2d2d2d;
      margin-bottom: .6rem;
    }

    .error-message {
      color: #6b6b6b;
      font-size: .96rem;
      line-height: 1.6;
      margin-bottom: 2rem;
    }

    .btn-home {
      background: var(--primary);
      border-color: var(--primary);
      color: #fff;
      border-radius: 10px;
      padding: .65rem 1.6rem;
      font-weight: 600;
      font-size: .95rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      transition: background .2s, transform .15s;
    }
    .btn-home:hover {
      background: var(--primary-dark);
      color: #fff;
      transform: translateY(-1px);
    }

    .btn-back {
      background: transparent;
      border: 2px solid var(--primary-light);
      color: var(--primary);
      border-radius: 10px;
      padding: .6rem 1.4rem;
      font-weight: 600;
      font-size: .95rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      margin-right: .6rem;
      transition: border-color .2s, color .2s, transform .15s;
    }
    .btn-back:hover {
      border-color: var(--primary);
      color: var(--primary-dark);
      transform: translateY(-1px);
    }

    .divider {
      border: none;
      border-top: 1px solid #f0e8e2;
      margin: 2rem 0 1.5rem;
    }

    .error-ref {
      font-size: .78rem;
      color: #b0b0b0;
      letter-spacing: .3px;
    }
  </style>
</head>
<body>
  <div class="error-card">
    @yield('content')
    <hr class="divider">
    <p class="error-ref mb-0">
      Reference: {{ date('YmdHis') }}-@yield('code', '500')
      &nbsp;·&nbsp;
      If the issue persists, please contact support.
    </p>
  </div>
</body>
</html>
