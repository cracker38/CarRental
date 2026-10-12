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

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            $result = Auth::attempt($email, $password);

            // Support either a boolean result or a result array from Auth::attempt().
            $success = $result === true
                || (is_array($result) && (bool) ($result['success'] ?? false));

            if ($success || is_logged_in()) {
                redirect('admin/dashboard.php');
            }

            if (is_array($result) && isset($result['message']) && is_string($result['message'])) {
                $error = $result['message'];
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
    <title>Sign in | CarRental</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #172033;
            --muted: #697386;
            --line: #e3e8f0;
            --primary: #2457d6;
            --primary-dark: #1a43ad;
            --surface: #ffffff;
            --page: #f3f6fb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 16px;
            background:
                radial-gradient(circle at 15% 15%, rgba(36,87,214,.11), transparent 30%),
                radial-gradient(circle at 90% 85%, rgba(14,165,160,.10), transparent 28%),
                var(--page);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .shell { width: 100%; max-width: 980px; }
        .brand {
            display: flex; align-items: center; gap: 12px;
            margin: 0 0 18px 4px; font-weight: 800; letter-spacing: -.03em; font-size: 21px;
        }
        .brand-mark {
            display: grid; place-items: center; width: 42px; height: 42px;
            border-radius: 13px; color: white; background: linear-gradient(145deg, #2f6cf2, #193fa9);
            box-shadow: 0 8px 20px rgba(36,87,214,.25);
        }
        .panel {
            display: grid; grid-template-columns: 1fr 1fr; overflow: hidden;
            background: var(--surface); border: 1px solid rgba(218,225,237,.9);
            border-radius: 24px; box-shadow: 0 24px 70px rgba(24,39,75,.12);
        }
        .intro {
            position: relative; overflow: hidden; padding: 54px 42px;
            color: #fff; background: linear-gradient(145deg, #122b69, #2457d6 70%, #2879d4);
            display: flex; flex-direction: column; justify-content: space-between; min-height: 510px;
        }
        .intro:before, .intro:after {
            content: ""; position: absolute; border: 1px solid rgba(255,255,255,.15); border-radius: 50%;
            width: 330px; height: 330px; right: -140px; top: -100px;
        }
        .intro:after { width: 230px; height: 230px; right: -65px; top: -50px; }
        .intro-content, .intro-foot { position: relative; z-index: 1; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px; margin-bottom: 22px;
            color: #dbe7ff; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .13em;
        }
        h1 { margin: 0 0 18px; max-width: 360px; font-size: clamp(30px, 4vw, 42px); line-height: 1.12; letter-spacing: -.045em; }
        .intro p { max-width: 350px; margin: 0; color: #dce7ff; font-size: 15px; line-height: 1.8; }
        .feature-list { display: grid; gap: 14px; margin-top: 35px; }
        .feature { display: flex; gap: 11px; align-items: center; color: #f3f7ff; font-size: 13px; }
        .check {
            display: grid; place-items: center; flex: 0 0 25px; height: 25px; border-radius: 8px;
            background: rgba(255,255,255,.15); font-weight: 800;
        }
        .intro-foot { color: #dce7ff; font-size: 12px; }
        .form-side { padding: 54px 44px; display: flex; flex-direction: column; justify-content: center; }
        .form-side h2 { margin: 0 0 8px; font-size: 27px; letter-spacing: -.035em; }
        .subtitle { margin: 0 0 30px; color: var(--muted); font-size: 14px; line-height: 1.6; }
        label { display: block; margin: 0 0 8px; font-size: 13px; font-weight: 700; }
        .field { margin-bottom: 19px; }
        input {
            width: 100%; padding: 14px 15px; border: 1px solid var(--line); border-radius: 11px;
            outline: none; background: #fbfcfe; color: var(--ink); font: inherit; font-size: 14px;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus { border-color: #7296f3; box-shadow: 0 0 0 4px rgba(36,87,214,.10); background: white; }
        .submit {
            width: 100%; border: 0; border-radius: 11px; padding: 15px 18px; margin-top: 5px;
            background: var(--primary); color: #fff; font: inherit; font-weight: 750; cursor: pointer;
            box-shadow: 0 8px 18px rgba(36,87,214,.20); transition: background .15s, transform .15s;
        }
        .submit:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .error {
            margin: 0 0 20px; padding: 12px 14px; border: 1px solid #fecaca; border-radius: 10px;
            background: #fff1f2; color: #9f1239; font-size: 13px; line-height: 1.55;
        }
        .security { display: flex; justify-content: center; align-items: center; gap: 7px; margin-top: 22px; color: var(--muted); font-size: 12px; }
        .hint { margin-top: 24px; text-align: center; color: var(--muted); font-size: 12px; line-height: 1.6; }
        @media (max-width: 760px) {
            .panel { grid-template-columns: 1fr; }
            .intro { min-height: auto; padding: 34px 28px; }
            .intro h1 { font-size: 31px; }
            .feature-list { grid-template-columns: 1fr; margin-top: 24px; }
            .intro-foot { margin-top: 28px; }
            .form-side { padding: 36px 28px; }
        }
    </style>
</head>
<body>
<main class="shell">
    <div class="brand">
        <div class="brand-mark" aria-hidden="true">CR</div>
        <span>CarRental <span style="font-weight:500;color:#697386">Management</span></span>
    </div>

    <section class="panel" aria-label="Sign in">
        <aside class="intro">
            <div class="intro-content">
                <div class="eyebrow"><span aria-hidden="true">✦</span> Rental operations portal</div>
                <h1>Everything for your fleet, in one place.</h1>
                <p>Sign in to manage reservations, rental agreements, vehicle handovers, invoices and payments.</p>
                <div class="feature-list">
                    <div class="feature"><span class="check">✓</span><span>Rental agreements and vehicle records</span></div>
                    <div class="feature"><span class="check">✓</span><span>Invoices, balances and payment tracking</span></div>
                    <div class="feature"><span class="check">✓</span><span>Secure access for authorized staff</span></div>
                </div>
            </div>
            <div class="intro-foot">A clearer way to manage every rental.</div>
        </aside>

        <div class="form-side">
            <h2>Welcome back</h2>
            <p class="subtitle">Enter your staff account details to continue.</p>

            <?php if ($error !== ''): ?>
                <div class="error" role="alert"><?= login_page_escape($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= login_page_escape(base_url('login.php')) ?>" autocomplete="on">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="<?= login_page_escape($email) ?>"
                           placeholder="you@example.com" autocomplete="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" placeholder="Enter your password"
                           autocomplete="current-password" required>
                </div>
                <button class="submit" type="submit">Sign in to dashboard <span aria-hidden="true">→</span></button>
            </form>

            <div class="security"><span aria-hidden="true">🔒</span> Your session is protected with secure sign-in.</div>
            <div class="hint">If you cannot sign in, contact the person who manages your CarRental user accounts.</div>
        </div>
    </section>
</main>
</body>
</html>
