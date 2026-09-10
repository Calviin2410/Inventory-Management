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
		background: #fff;
	}

	aside {
		display: flex;
		flex-direction: column;
		justify-content: space-between;

		padding: 48px 9vw 48px 5vw;

		background: linear-gradient(145deg, #172752, #284fc5);

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

	main {
		display: grid;

		place-items: center;

		padding: 40px;
	}

	form {
		width: min(390px, 100%);
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

		background: #315ee7;
		color: #fff;

		font-weight: 750;

		cursor: pointer;
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

	@media (max-width: 760px) {
		.auth-page {
			grid-template-columns: 1fr;
		}

		aside {
			display: none;
		}

		main {
			padding: 28px;
		}
	}
</style>
