<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --navy-dark: #0f2942;
  --sky-blue: #0ea5e9;
  --bg: #F1F5F9;
  --surface: #FFFFFF;
  --border: #E2E8F0;
  --border-focus: #0ea5e9;
  --text: #1A1C1E;
  --text-secondary: #64748B;
  --text-muted: #64748B;
  --accent: #0f2942;
  --danger: #EF4444;
  --danger-light: #FEF0EF;
  --shadow: 0 1px 2px rgba(0,0,0,0.05), 0 4px 16px rgba(0,0,0,0.10);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
}
.brand-logo-img {
  width: 46px;
  height: 46px;
  object-fit: contain;
  border-radius: 10px;
}
.left-panel {
  width: 50%;
  min-height: 100vh;
  background: var(--navy-dark);
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 2.5rem;
  overflow: hidden;
  flex-shrink: 0;
}
.left-panel::before {
  content: '';
  position: absolute;
  top: -120px; right: -120px;
  width: 420px; height: 420px;
  border-radius: 50%;
  background: rgba(14,165,233,0.10);
}
.left-panel::after {
  content: '';
  position: absolute;
  top: -60px; right: -180px;
  width: 360px; height: 360px;
  border-radius: 50%;
  border: 1px solid rgba(14,165,233,0.15);
}
.brand-row { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
.brand-logo {
  width: 46px; height: 46px;
  background: white; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 800;
  color: var(--navy-dark); letter-spacing: -1px;
}
.brand-name { font-size: 16px; font-weight: 600; color: white; }
.left-content { position: relative; z-index: 1; }
.left-accent-line { width: 36px; height: 3px; background: var(--sky-blue); border-radius: 2px; margin-bottom: 1.5rem; }
.left-heading { font-size: clamp(28px, 3vw, 38px); font-weight: 700; color: white; line-height: 1.2; letter-spacing: -0.02em; margin-bottom: 1rem; }
.left-heading span { color: var(--sky-blue); }
.left-sub { font-size: 14px; color: rgba(255,255,255,0.55); line-height: 1.6; max-width: 320px; }
.left-footer { font-size: 12px; color: rgba(255,255,255,0.3); position: relative; z-index: 1; }
.right-panel {
  flex: 1;
  min-height: 100vh;
  background: var(--bg);
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2rem 2.5rem;
}
.login-box { width: 100%; max-width: 420px; }
.login-header { margin-bottom: 2rem; }
.login-title { font-size: 22px; font-weight: 600; letter-spacing: -0.02em; margin-bottom: 0.4rem; }
.login-sub { font-size: 14px; color: var(--text-secondary); }
.card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; box-shadow: var(--shadow); overflow: hidden; padding: 2rem;
}
.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
label { font-size: 11.5px; font-weight: 600; color: var(--text-secondary); letter-spacing: 0.04em; text-transform: uppercase; }
input {
  font-family: 'DM Sans', sans-serif; font-size: 14px;
  color: var(--text); background: var(--surface);
  border: 1px solid var(--border); border-radius: 8px;
  padding: 11px 12px; outline: none; width: 100%; transition: all 0.15s;
}
input:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
.input-wrap { position: relative; }
.input-wrap input { padding-right: 40px; }
.eye-btn {
  position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--text-muted); display: flex; align-items: center; padding: 4px;
}
.row-between { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-secondary); cursor: pointer; }
.remember input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
.forgot-link { font-size: 13px; color: var(--accent); text-decoration: none; font-weight: 600; }
.forgot-link:hover { text-decoration: underline; }
.btn-login {
  width: 100%; padding: 12px;
  background: var(--accent); color: white;
  border: none; border-radius: 8px;
  font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-login:hover { background: #1a3a5c; box-shadow: 0 4px 12px rgba(15,41,66,0.25); }
.divider { display: flex; align-items: center; gap: 12px; margin: 1.5rem 0; }
.divider-line { flex: 1; height: 1px; background: var(--border); }
.divider-text { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.register-link-row { text-align: center; font-size: 13.5px; color: var(--text-secondary); margin-top: 1.5rem; }
.register-link-row a { color: var(--accent); font-weight: 600; text-decoration: none; }
.err-msg {
  background: var(--danger-light); border: 1px solid #F5C6C3;
  border-radius: 8px; padding: 10px 14px;
  font-size: 13px; color: var(--danger);
  display: flex; align-items: center; gap: 8px; margin-bottom: 1.25rem;
}
.err-msg.hidden { display: none; }
@media (max-width: 768px) {
  body { flex-direction: column; }
  .left-panel { width: 100%; min-height: auto; padding: 2rem; }
  .left-panel::before, .left-panel::after { display: none; }
  .left-content { padding: 2rem 0 1rem; }
  .left-footer { display: none; }
  .right-panel { padding: 2rem 1.25rem; }
}
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="brand-row">
    <img src="{{ asset('img/Logo_DKJ.jpeg') }}" alt="DKJ Logo" class="brand-logo-img">
    <div class="brand-name">PT. Dunia Kimia Jaya</div>
  </div>

  <div class="left-content">
    <div class="left-accent-line"></div>
    <div class="left-heading">Hello, <span>Welcome</span><br>Back!</div>
    <div class="left-sub">Sign in to your account to access the system.</div>
  </div>

  <div class="left-footer">© 2026 PT. Dunia Kimia Jaya</div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <div class="login-box">
    <div class="login-header">
      <div class="login-title">Welcome Back</div>
      <div class="login-sub">Sign in to the purchasing system to continue</div>
    </div>

    @if($errors->any())
    <div class="err-msg">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
    @endif

    <div class="card">
      <form method="POST" action="{{ route('login.post') }}">
        @csrf
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email">
        </div>

        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="passInput" placeholder="Enter your password" autocomplete="current-password">
            <button class="eye-btn" onclick="togglePass()" type="button" id="eyeBtn">
              <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
        </div>

        <div class="row-between">
          <label class="remember">
            <input type="checkbox" name="remember"> Remember me
          </label>
          <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
        </div>

        <button class="btn-login" type="submit">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
          Sign In
        </button>

        <div class="divider">
          <div class="divider-line"></div>
          <div class="divider-text">or</div>
          <div class="divider-line"></div>
        </div>

        <div class="register-link-row">
          Don't have an account? <a href="{{ route('register') }}">Register now</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function togglePass() {
    const inp = document.getElementById('passInput');
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }
</script>
</body>
</html>