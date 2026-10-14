<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (is_logged_in()) {
    redirect('admin/rental-operations.php');
}

$error = '';
$email = '';

if (is_post()) {
    Csrf::requireValid();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            $result = Auth::attempt($email, $password);

            $success = $result === true
                || (is_array($result) && (bool) ($result['ok'] ?? false));

            if ($success || is_logged_in()) {
                redirect('admin/rental-operations.php');
            }

            if (is_array($result) && isset($result['error']) && is_string($result['error'])) {
                $error = $result['error'];
            } else {
                $error = 'The email address or password is incorrect, or the account is inactive.';
            }
        } catch (Throwable $exception) {
            // Keep technical details out of the login page.
            error_log('Login error: ' . $exception->getMessage());
            $error = 'We could not sign you in. Please check your database connection and try again.';
        }
    }
}

function login_page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#172b5c">
    <title>Sign in | CarRental</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #172033;
            --muted: #69758a;
            --line: #e4e9f1;
            --primary: #2457d6;
            --primary-dark: #1945b0;
            --surface: #ffffff;
            --page: #f4f6fb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 36px 20px;
            background:
                radial-gradient(circle at 9% 12%, rgba(67,101,207,.10), transparent 29rem),
                radial-gradient(circle at 93% 88%, rgba(105,91,202,.08), transparent 26rem),
                var(--page);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .shell { width: 100%; max-width: 1020px; }
        .brand {
            display: flex; align-items: center; gap: 11px;
            margin: 0 0 19px 3px; font-weight: 850; letter-spacing: -.04em; font-size: 20px;
        }
        .brand-mark {
            display: grid; place-items: center; width: 41px; height: 41px;
            border-radius: 12px; color: white; background: linear-gradient(145deg, #3776f6, #183c9d);
            box-shadow: 0 7px 17px rgba(36,87,214,.22); font-size: 14px; letter-spacing: -.03em;
        }
        .brand-copy { display: block; }
        .brand-copy small {
            display: block; margin-top: -2px; color: var(--muted); font-size: 9px;
            font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
        }
        .panel {
            display: grid; grid-template-columns: 1fr 1fr; overflow: hidden;
            background: var(--surface); border: 1px solid rgba(218,225,237,.9);
            border-radius: 23px; box-shadow: 0 24px 70px rgba(24,39,75,.11);
        }
        .intro {
            position: relative; overflow: hidden; padding: 52px 44px;
            color: #fff; background: linear-gradient(145deg, #17274f, #244caa 72%, #3d66c6);
            display: flex; flex-direction: column; justify-content: space-between; min-height: 510px;
        }
        .intro:before, .intro:after {
            content: ""; position: absolute; border: 1px solid rgba(255,255,255,.15); border-radius: 50%;
            width: 340px; height: 340px; right: -150px; top: -115px;
        }
        .intro:after { width: 230px; height: 230px; right: -66px; top: -61px; }
        .intro-content, .intro-foot { position: relative; z-index: 1; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 9px; margin-bottom: 21px;
            color: #d9e4ff; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .14em;
        }
        .eyebrow-dot { width: 7px; height: 7px; border-radius: 50%; background: #9db7ff; box-shadow: 0 0 0 5px rgba(157,183,255,.13); }
        .intro h1 { margin: 0 0 16px; max-width: 370px; font-size: clamp(30px, 4vw, 41px); line-height: 1.12; letter-spacing: -.045em; }
        .intro p { max-width: 360px; margin: 0; color: #dce6ff; font-size: 14px; line-height: 1.8; }
        .feature-list { display: grid; gap: 15px; margin-top: 34px; }
        .feature { display: flex; gap: 11px; align-items: center; color: #f3f6ff; font-size: 12px; }
        .check {
            display: grid; place-items: center; flex: 0 0 24px; height: 24px; border-radius: 8px;
            background: rgba(255,255,255,.13); color: #d9e4ff; font-size: 12px; font-weight: 800;
        }
        .intro-foot { color: #dce7ff; font-size: 11px; }
        .form-side { padding: 54px clamp(30px, 5vw, 53px); display: flex; flex-direction: column; justify-content: center; }
        .form-kicker { margin: 0 0 10px; color: var(--primary); font-size: 10px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        .form-side h2 { margin: 0 0 8px; font-size: 28px; letter-spacing: -.04em; }
        .subtitle { margin: 0 0 29px; color: var(--muted); font-size: 13px; line-height: 1.7; }
        label { display: block; margin: 0 0 8px; font-size: 12px; font-weight: 750; }
        .field { margin-bottom: 18px; }
        input {
            width: 100%; min-height: 49px; padding: 13px 14px; border: 1px solid var(--line); border-radius: 10px;
            outline: none; background: #fbfcfe; color: var(--ink); font: inherit; font-size: 13px;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        input::placeholder { color: #a0a9b8; }
        input:focus { border-color: #7898ed; box-shadow: 0 0 0 4px rgba(36,87,214,.10); background: white; }
        input:focus-visible, button:focus-visible { outline: 3px solid rgba(36,87,214,.28); outline-offset: 2px; }
        .password-control { position: relative; }
        .password-control input { padding-right: 67px; }
        .password-toggle {
            position: absolute; top: 50%; right: 12px; transform: translateY(-50%);
            border: 0; padding: 7px 3px; background: transparent; color: #53627b;
            font: inherit; font-size: 11px; font-weight: 750; cursor: pointer;
        }
        .password-toggle:hover { color: var(--primary); }
        .submit {
            width: 100%; min-height: 49px; border: 0; border-radius: 10px; padding: 13px 18px; margin-top: 4px;
            background: linear-gradient(105deg, #285bd0, #244db5); color: #fff;
            font: inherit; font-size: 13px; font-weight: 750; cursor: pointer;
            box-shadow: 0 8px 18px rgba(36,87,214,.18); transition: background .15s ease, transform .15s ease, box-shadow .15s ease;
        }
        .submit:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 10px 22px rgba(36,87,214,.24); }
        .error {
            margin: 0 0 19px; padding: 12px 13px; border: 1px solid #f2c8cc; border-radius: 9px;
            background: #fff5f5; color: #9b3440; font-size: 12px; line-height: 1.55;
        }
        .security { display: flex; justify-content: center; align-items: center; gap: 7px; margin-top: 22px; color: var(--muted); font-size: 10px; }
        .security-dot { width: 7px; height: 7px; border-radius: 50%; background: #1b9a72; }
        .hint { margin-top: 20px; text-align: center; color: var(--muted); font-size: 11px; line-height: 1.65; }
        @media (max-width: 720px) {
            body { padding: 24px 16px; }
            .shell { max-width: 480px; }
            .panel { grid-template-columns: 1fr; }
            .intro { min-height: auto; padding: 31px 29px 27px; }
            .intro h1 { max-width: 420px; font-size: 30px; }
            .intro p { max-width: 420px; }
            .feature-list { gap: 11px; margin-top: 22px; }
            .intro-foot { margin-top: 25px; }
            .form-side { padding: 34px 29px 31px; }
        }
        @media (max-width: 380px) {
            body { padding: 18px 12px; }
            .intro, .form-side { padding-right: 22px; padding-left: 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
<main class="shell">
    <div class="brand">
        <div class="brand-mark" aria-hidden="true">CR</div>
        <span class="brand-copy">CarRental<small>Management workspace</small></span>
    </div>

    <section class="panel" aria-labelledby="sign-in-title">
        <aside class="intro">
            <div class="intro-content">
                <div class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> Rental operations portal</div>
                <h1>Every rental, under control.</h1>
                <p>Sign in to manage reservations, rental agreements, vehicle handovers, invoices and payments.</p>
                <div class="feature-list">
                    <div class="feature"><span class="check" aria-hidden="true">✓</span><span>Agreements and vehicle records</span></div>
                    <div class="feature"><span class="check" aria-hidden="true">✓</span><span>Invoices and payment tracking</span></div>
                    <div class="feature"><span class="check" aria-hidden="true">✓</span><span>Access for authorized staff</span></div>
                </div>
            </div>
            <div class="intro-foot">One workspace for your rental operations.</div>
        </aside>

        <div class="form-side">
            <p class="form-kicker">Staff portal</p>
            <h2 id="sign-in-title">Welcome back</h2>
            <p class="subtitle">Sign in with your staff account to continue.</p>

            <?php if ($error !== ''): ?>
                <div class="error" id="sign-in-error" role="alert" aria-live="polite"><?= login_page_escape($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= login_page_escape(base_url('login.php')) ?>" autocomplete="on">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="<?= login_page_escape($email) ?>"
                           placeholder="you@example.com" autocomplete="username" autocapitalize="none" spellcheck="false"
                           required autofocus<?= $error !== '' ? ' aria-describedby="sign-in-error"' : '' ?>>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="password-control">
                        <input id="password" name="password" type="password" placeholder="Enter your password"
                               autocomplete="current-password" required<?= $error !== '' ? ' aria-describedby="sign-in-error"' : '' ?>>
                        <button class="password-toggle" type="button" aria-controls="password" aria-label="Show password" aria-pressed="false">Show</button>
                    </div>
                </div>
                <button class="submit" type="submit">Sign in to workspace</button>
            </form>

            <div class="security"><span class="security-dot" aria-hidden="true"></span> Secure sign-in for authorized staff</div>
            <div class="hint">If you cannot sign in, contact the person who manages your CarRental user accounts.</div>
        </div>
    </section>
</main>
<script>
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');

    passwordToggle.addEventListener('click', () => {
        const isVisible = passwordInput.type === 'text';
        passwordInput.type = isVisible ? 'password' : 'text';
        passwordToggle.textContent = isVisible ? 'Show' : 'Hide';
        passwordToggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        passwordToggle.setAttribute('aria-pressed', String(!isVisible));
    });
</script>
</body>
</html>
