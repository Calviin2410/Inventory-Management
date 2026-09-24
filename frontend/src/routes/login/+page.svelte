<script>
	import { onMount } from "svelte";
	import { goto } from "$app/navigation";
	import { api, getToken, setToken } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";

	let email = $state("");
	let password = $state("");
	let remember = $state(false);

	let errorMessage = $state("");
	let loading = $state(false);

	onMount(async () => {
		if (!getToken()) return;

		loading = true;
		try {
			const currentUser = await api.me();
			user.set(currentUser);
			await goto("/dashboard");
		} catch {
			setToken(null);
		} finally {
			loading = false;
		}
	});

	async function handleLogin() {
		errorMessage = "";
		loading = true;

		try {
			const data = await api.login(email, password, remember);

			if (!data?.token) {
				throw new Error("Login succeeded but token was not returned.");
			}

			setToken(data.token, remember);

			user.set(data.user ?? null);

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
		<div class="brand-stage">
			<div class="auth-brand">
				<img src="/images/tks-brand-logo.png" alt="TKS Waste Management" />
			</div>
		</div>

		<small> TKS Waste Management · Operations System </small>
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
					autocomplete="email"
					placeholder="you@company.com"
					bind:value={email}
					required
				/>
			</label>

			<label>
				Password

				<input
					type="password"
					autocomplete="current-password"
					placeholder="Enter your password"
					bind:value={password}
					required
				/>
			</label>

			<label class="remember-option">
				<input type="checkbox" bind:checked={remember} />
				<span>Remember me</span>
			</label>

			<button type="submit" disabled={loading}>
				{loading ? "Signing in…" : "Sign in"}
			</button>
			<p class="secure-note">🔒 Your account is protected by secure authentication</p>

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

		padding: 48px 5vw;

		position: relative;
		overflow: hidden;
		background: linear-gradient(145deg, #13224a 0%, #234bbd 70%, #2f65dc 100%);

		color: #fff;
	}

	.brand-stage {
		display: grid;
		flex: 1;
		place-items: center;
	}

	.auth-brand {
		width: min(430px, 88%);
		padding: 14px 18px;
		border-radius: 16px;
		background: #fff;
		box-shadow: 0 18px 45px rgb(5 18 55 / 16%);
	}

	.auth-brand img {
		display: block;
		width: 100%;
		height: auto;
	}

	aside small {
		color: #aabaf3;
	}

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

	.remember-option {
		flex-direction: row;
		align-items: center;
		gap: 9px;
		margin: 4px 0 16px;
		font-weight: 600;
		cursor: pointer;
	}

	.remember-option input {
		width: 16px;
		height: 16px;
		margin: 0;
		accent-color: #315ee7;
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
