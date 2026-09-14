<script>
	import { goto } from "$app/navigation";
	import { api, setToken } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";
	let name = $state("");
	let email = $state("");
	let password = $state("");
	let errorMessage = $state("");
	let loading = $state(false);

	async function handleRegister() {
		errorMessage = "";
		loading = true;
		try {
			const data = await api.register(name, email, password);
			setToken(data.token);
			user.set(data.user);
			goto("/dashboard");
		} catch (err) {
			errorMessage = err instanceof Error ? err.message : "Registration failed.";
		} finally { loading = false; }
	}
</script>

<div class="auth-page">
	<aside>
		<a class="auth-brand" href="/login"><span>IM</span><strong>Inventory Manager</strong></a>
		<div class="brand-message">
			<p>GET STARTED</p>
			<h1>Build a clearer view of your operations.</h1>
			<h2>Create your workspace account and manage every rental, customer and invoice with confidence.</h2>
			<div class="feature-list">
				<span><i>01</i><b>Organised</b><small>Keep every record in one place</small></span>
				<span><i>02</i><b>Efficient</b><small>Reduce repetitive daily work</small></span>
				<span><i>03</i><b>Reliable</b><small>Make decisions from current data</small></span>
			</div>
		</div>
		<small>Inventory Manager · Built for everyday teams</small>
	</aside>
	<main>
		<form onsubmit={(event) => { event.preventDefault(); handleRegister(); }}>
			<p class="eyebrow">CREATE ACCOUNT</p>
			<h3>Set up your workspace</h3>
			<p class="intro">Enter your details to create a secure account.</p>
			{#if errorMessage}<div class="auth-error">{errorMessage}</div>{/if}
			<label>Full name<input type="text" placeholder="Your full name" bind:value={name} autocomplete="name" required /></label>
			<label>Email address<input type="email" placeholder="you@company.com" bind:value={email} autocomplete="email" required /></label>
			<label>Password<input type="password" placeholder="Minimum 8 characters" bind:value={password} minlength="8" autocomplete="new-password" required /><small>Use at least 8 characters.</small></label>
			<button type="submit" disabled={loading}>{loading ? "Creating account…" : "Create account"}</button>
			<p class="secure-note">🔒 Your information is securely protected</p>
			<p class="switch">Already have an account? <a href="/login">Sign in</a></p>
		</form>
	</main>
</div>

<style>
	.auth-page { display: grid; min-height: 100vh; grid-template-columns: 1.1fr 1fr; background: #f5f7fa; }
	aside { display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; padding: 48px 9vw 48px 5vw; background: linear-gradient(145deg, #13224a 0%, #234bbd 70%, #2f65dc 100%); color: white; }
	.auth-brand { display: flex; align-items: center; gap: 10px; color: white; text-decoration: none; }
	.auth-brand span { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 10px; background: white; color: #315ee7; font-size: 12px; font-weight: 800; }
	.auth-brand strong { font-size: 14px; }
	.brand-message > p { margin: 0; font-size: 10px; font-weight: 800; letter-spacing: .16em; opacity: .72; }
	.brand-message h1 { max-width: 620px; margin: 16px 0; font-size: clamp(35px, 4vw, 56px); line-height: 1.08; letter-spacing: -.045em; }
	.brand-message h2 { max-width: 520px; margin: 0; color: #cbd6ff; font-size: 15px; font-weight: 400; line-height: 1.65; }
	.feature-list { display: flex; gap: 28px; margin-top: 35px; }
	.feature-list span { display: grid; grid-template-columns: 27px auto; column-gap: 8px; }
	.feature-list i { grid-row: 1 / 3; color: #8eabff; font-style: normal; font-size: 10px; font-weight: 800; }
	.feature-list b { font-size: 11px; }
	.feature-list small { margin-top: 3px; color: #aebff1; font-size: 9px; }
	aside > small { color: #aabaf3; }
	main { display: grid; place-items: center; padding: 40px; background: radial-gradient(circle at 100% 0%, #e8eeff 0, transparent 34%); }
	form { width: min(410px, 100%); padding: 36px 38px; border: 1px solid #e3e8ef; border-radius: 14px; background: rgb(255 255 255 / 96%); box-shadow: 0 20px 55px rgb(31 47 76 / 10%); }
	.eyebrow { margin: 0; color: #315ee7; font-size: 10px; font-weight: 800; letter-spacing: .13em; }
	h3 { margin: 7px 0 0; color: #182235; font-size: 27px; letter-spacing: -.03em; }
	.intro { margin: 9px 0 22px; color: #7a8496; font-size: 12px; }
	label { display: flex; flex-direction: column; gap: 7px; margin: 14px 0; color: #344054; font-size: 12px; font-weight: 700; }
	label small { color: #99a2b1; font-size: 9px; font-weight: 500; }
	input { height: 44px; padding: 0 13px; border: 1px solid #ccd4e0; border-radius: 9px; outline: 0; background: #fff; }
	input:focus { border-color: #315ee7; box-shadow: 0 0 0 3px rgb(49 94 231 / 12%); }
	button { width: 100%; height: 45px; margin-top: 6px; border: 0; border-radius: 9px; background: linear-gradient(180deg, #3264db, #2551bd); color: white; font-weight: 750; cursor: pointer; box-shadow: 0 7px 16px rgb(37 81 189 / 20%); }
	button:disabled { opacity: .65; cursor: not-allowed; }
	.auth-error { margin: 16px 0 6px; padding: 11px; border-radius: 8px; background: #fef2f2; color: #b42318; font-size: 11px; }
	.secure-note { margin: 13px 0 0; color: #98a2b2; text-align: center; font-size: 9px; }
	.switch { margin: 17px 0 0; color: #7a8496; text-align: center; font-size: 12px; }
	.switch a { color: #315ee7; font-weight: 750; text-decoration: none; }
	@media (max-width: 900px) { .feature-list { display: grid; } }
	@media (max-width: 760px) { .auth-page { grid-template-columns: 1fr; } aside { display: none; } main { padding: 22px; } form { padding: 28px 24px; } }
</style>
