<script>
	import { goto } from "$app/navigation";
	import { api, setToken } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";

	let email = $state("");
	let password = $state("");

	let errorMessage = $state("");
	let loading = $state(false);

	async function handleLogin() {
		errorMessage = "";
		loading = true;

		try {
			const data = await api.login(email, password);

			console.log("Login response:", data);

			if (!data?.token) {
				throw new Error("Login succeeded but token was not returned.");
			}

			setToken(data.token);

			user.set(data.user ?? null);

			console.log("Saved token:", localStorage.getItem("token"));

			goto("/dashboard");
		} catch (error) {
			console.error("Login failed:", error);

			errorMessage =
				error instanceof Error ? error.message : "Sign in failed";
		} finally {
			loading = false;
		}
	}
</script>

<div class="auth-page">
	<aside>
		<div class="auth-brand">
			<span>IM</span>

			Inventory Manager
		</div>

		<div>
			<p>SMARTER OPERATIONS</p>

			<h1>
				Everything in stock.
				<br />
				Everything in sight.
			</h1>

			<h2>
				Manage products, rentals and invoices from one focused
				workspace.
			</h2>
			<div class="feature-list">
				<span><i>✓</i> Real-time inventory visibility</span>
				<span><i>✓</i> Centralised invoice management</span>
				<span><i>✓</i> Secure role-based access</span>
			</div>
		</div>

		<small> Inventory Manager · Built for everyday teams </small>
	</aside>

	<main>
		<form
			onsubmit={(event) => {
				event.preventDefault();

				handleLogin();
			}}
		>
			<p class="eyebrow">WELCOME BACK</p>

			<h3>Sign in to your account</h3>

			<p class="intro">
				Enter your details to continue to the workspace.
			</p>

			{#if errorMessage}
				<div class="auth-error">
					{errorMessage}
				</div>
			{/if}

			<label>
				Email address

				<input
					type="email"
					placeholder="you@company.com"
					bind:value={email}
					required
				/>
			</label>

			<label>
				Password

				<input
					type="password"
					placeholder="Enter your password"
					bind:value={password}
					required
				/>
			</label>

			<button type="submit" disabled={loading}>
				{loading ? "Signing in…" : "Sign in"}
			</button>
			<p class="secure-note">🔒 Your account is protected by secure authentication</p>

			<p class="switch">
				New here?

				<a href="/register"> Create an account </a>
			</p>
		</form>
	</main>
</div>

<style>
	.auth-page {
		display: grid;
		min-height: 100vh;
		grid-template-columns: 1.1fr 1fr;
		background: #f5f7fa;
	}

	aside {
		display: flex;
		flex-direction: column;
		justify-content: space-between;

		padding: 48px 9vw 48px 5vw;

		position: relative;
		overflow: hidden;
		background: linear-gradient(145deg, #13224a 0%, #234bbd 70%, #2f65dc 100%);

		color: #fff;
	}

	.auth-brand {
		display: flex;
		align-items: center;
		gap: 10px;

		font-weight: 750;
	}

	.auth-brand span {
		display: grid;

		width: 36px;
		height: 36px;

		place-items: center;

		border-radius: 10px;

		background: #fff;
		color: #315ee7;

		font-size: 12px;
	}

	aside p {
		font-size: 11px;
		font-weight: 800;

		letter-spacing: 0.15em;

		opacity: 0.7;
	}

	aside h1 {
		margin: 16px 0;

		font-size: clamp(36px, 4vw, 58px);

		line-height: 1.08;

		letter-spacing: -0.045em;
	}

	aside h2 {
		max-width: 520px;

		margin: 0;

		color: #cbd6ff;

		font-size: 16px;
		font-weight: 400;

		line-height: 1.6;
	}

	aside small {
		color: #aabaf3;
	}
	.feature-list { display: grid; gap: 13px; margin-top: 34px; color: #dce5ff; font-size: 12px; }
	.feature-list span { display: flex; align-items: center; gap: 9px; }
	.feature-list i { display: grid; width: 21px; height: 21px; place-items: center; border-radius: 50%; background: rgb(255 255 255 / 13%); color: white; font-style: normal; font-size: 10px; }

	main {
		display: grid;

		place-items: center;

		padding: 40px;
		background: radial-gradient(circle at 100% 0%, #e8eeff 0, transparent 34%);
	}

	form {
		width: min(390px, 100%);
		padding: 38px;
		border: 1px solid #e3e8ef;
		border-radius: 14px;
		background: rgb(255 255 255 / 96%);
		box-shadow: 0 20px 55px rgb(31 47 76 / 10%);
	}

	h3 {
		margin: 7px 0 0;

		font-size: 29px;

		letter-spacing: -0.03em;
	}

	.eyebrow {
		margin: 0;

		color: #315ee7;

		font-size: 11px;
		font-weight: 800;

		letter-spacing: 0.12em;
	}

	.intro {
		margin: 9px 0 28px;

		color: #7a8496;

		font-size: 13px;
	}

	label {
		display: flex;
		flex-direction: column;

		gap: 8px;

		margin: 17px 0;

		color: #344054;

		font-size: 13px;
		font-weight: 700;
	}

	input {
		height: 45px;

		padding: 0 13px;

		border: 1px solid #ccd4e0;

		border-radius: 9px;

		outline: 0;

		font-family: inherit;
	}

	input:focus {
		border-color: #315ee7;

		box-shadow: 0 0 0 3px rgb(49 94 231 / 12%);
	}

	button {
		width: 100%;
		height: 45px;

		margin-top: 7px;

		border: 0;
		border-radius: 9px;

		background: linear-gradient(180deg, #3264db, #2551bd);
		color: #fff;

		font-weight: 750;

		cursor: pointer;
		box-shadow: 0 7px 16px rgb(37 81 189 / 20%);
	}

	button:disabled {
		opacity: 0.65;

		cursor: not-allowed;
	}

	.switch {
		margin-top: 18px;

		text-align: center;

		color: #7a8496;

		font-size: 13px;
	}

	.switch a {
		color: #315ee7;

		font-weight: 750;

		text-decoration: none;
	}

	.auth-error {
		margin-top: 18px;

		padding: 11px;

		border-radius: 8px;

		background: #fef2f2;
		color: #b42318;

		font-size: 12px;
	}
	.secure-note { margin: 14px 0 0; color: #98a2b2; text-align: center; font-size: 9px; }

	@media (max-width: 760px) {
		.auth-page {
			grid-template-columns: 1fr;
		}

		aside {
			display: none;
		}

		main {
			padding: 22px;
		}
		form { padding: 28px 24px; }
	}
</style>
