<script>
	import PasswordVisibilityIcon from "$lib/PasswordVisibilityIcon.svelte";

	let {
		open = false,
		busy = false,
		errorMessage = "",
		oncancel = () => {},
		onconfirm = () => {},
	} = $props();

	let name = $state("");
	let email = $state("");
	let password = $state("");
	let passwordConfirmation = $state("");
	let showPassword = $state(false);
	let showPasswordConfirmation = $state(false);

	function close() {
		if (!busy) oncancel();
	}

	function submit() {
		onconfirm({
			name,
			email,
			password,
			password_confirmation: passwordConfirmation,
		});
	}

	$effect(() => {
		if (!open) {
			name = "";
			email = "";
			password = "";
			passwordConfirmation = "";
			showPassword = false;
			showPasswordConfirmation = false;
		}
	});
</script>

{#if open}
	<div class="backdrop" role="presentation" onclick={(event) => event.currentTarget === event.target && close()}>
		<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="add-staff-title">
			<p class="eyebrow">ACCESS MANAGEMENT</p>
			<h2 id="add-staff-title">Add staff account</h2>
			<p class="intro">Create login access for an employee. The account will have staff permissions only.</p>

			{#if errorMessage}<div class="error">{errorMessage}</div>{/if}

			<form onsubmit={(event) => { event.preventDefault(); submit(); }}>
				<label>Full name<input bind:value={name} autocomplete="name" required maxlength="255" /></label>
				<label>Email address<input type="email" bind:value={email} autocomplete="email" required /></label>
				<label>Temporary password<div class="password-field"><input type={showPassword ? "text" : "password"} bind:value={password} autocomplete="new-password" minlength="8" required /><button class="password-toggle" type="button" aria-label={showPassword ? "Hide password" : "Show password"} aria-pressed={showPassword} onclick={() => (showPassword = !showPassword)}><PasswordVisibilityIcon visible={showPassword} /></button></div></label>
				<label>Confirm password<div class="password-field"><input type={showPasswordConfirmation ? "text" : "password"} bind:value={passwordConfirmation} autocomplete="new-password" minlength="8" required /><button class="password-toggle" type="button" aria-label={showPasswordConfirmation ? "Hide password" : "Show password"} aria-pressed={showPasswordConfirmation} onclick={() => (showPasswordConfirmation = !showPasswordConfirmation)}><PasswordVisibilityIcon visible={showPasswordConfirmation} /></button></div></label>

				<div class="actions">
					<button class="btn" type="button" disabled={busy} onclick={close}>Cancel</button>
					<button class="btn primary" type="submit" disabled={busy}>{busy ? "Creating…" : "Create Staff"}</button>
				</div>
			</form>
		</div>
	</div>
{/if}

<style>
	.backdrop { position: fixed; z-index: 1000; inset: 0; display: grid; place-items: center; padding: 20px; background: rgb(15 23 42 / 52%); }
	.dialog { width: min(480px, 100%); padding: 26px; border-radius: 14px; background: #fff; box-shadow: 0 24px 70px rgb(15 23 42 / 25%); }
	.eyebrow { margin: 0; color: #315ee7; font-size: 10px; font-weight: 800; letter-spacing: .13em; }
	h2 { margin: 7px 0 0; color: #111827; font-size: 23px; }
	.intro { margin: 8px 0 20px; color: #64748b; font-size: 12px; line-height: 1.55; }
	form, label { display: flex; flex-direction: column; }
	form { gap: 14px; }
	label { gap: 6px; color: #344054; font-size: 11px; font-weight: 700; }
	input { height: 43px; padding: 0 12px; border: 1px solid #ccd4e0; border-radius: 8px; outline: none; }
	input:focus { border-color: #315ee7; box-shadow: 0 0 0 3px rgb(49 94 231 / 12%); }
	.password-field { position: relative; width: 100%; }
	.password-field input { width: 100%; padding-right: 44px; }
	.password-toggle { position: absolute; top: 50%; right: 6px; display: grid; width: 34px; height: 34px; padding: 0; place-items: center; transform: translateY(-50%); border: 0; background: transparent; color: #667085; cursor: pointer; }
	.password-toggle:hover { color: #315ee7; }
	.password-toggle:focus-visible { outline: 2px solid #315ee7; outline-offset: 1px; }
	.error { margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; font-size: 11px; }
	.actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 6px; }
	.primary { border-color: #315ee7; background: #315ee7; color: #fff; }
</style>
