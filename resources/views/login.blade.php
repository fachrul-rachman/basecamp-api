<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} — Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f4f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        form {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,.1);
            width: 320px;
        }
        h1 { font-size: 1.1rem; margin: 0 0 1.25rem; color: #1f2937; }
        label { display: block; font-size: .85rem; color: #374151; margin-bottom: .25rem; }
        input {
            width: 100%;
            padding: .5rem .6rem;
            margin-bottom: 1rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: .95rem;
        }
        button {
            width: 100%;
            padding: .6rem;
            background: #111827;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: .95rem;
            cursor: pointer;
        }
        button:disabled { opacity: .6; cursor: default; }
        #error {
            color: #b91c1c;
            font-size: .85rem;
            margin-bottom: 1rem;
            display: none;
        }
    </style>
</head>
<body>
    <form id="login-form">
        <h1>{{ config('app.name') }}</h1>
        <div id="error"></div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="username">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="submit">Log in</button>
    </form>

    <script>
        const form = document.getElementById('login-form');
        const errorBox = document.getElementById('error');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorBox.style.display = 'none';

            const button = form.querySelector('button');
            button.disabled = true;

            try {
                const response = await fetch('/api/v1/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        email: document.getElementById('email').value,
                        password: document.getElementById('password').value,
                    }),
                });

                const body = await response.json();

                if (!response.ok) {
                    throw new Error(body.message || 'Login failed.');
                }

                window.location.href = `/docs/api?token=${encodeURIComponent(body.data.token)}`;
            } catch (err) {
                errorBox.textContent = err.message;
                errorBox.style.display = 'block';
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
