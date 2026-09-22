<script>
	import { tick } from "svelte";

	let {
		open = false,
		staffName = "",
		busy = false,
		errorMessage = "",
		oncancel = () => {},
		onconfirm = () => {},
	} = $props();

	let dialogElement = $state();
	let passwordInput = $state();
	let password = $state("");
	let passwordConfirmation = $state("");
	let localError = $state("");
	let previouslyFocused;

	$effect(() => {
		if (!open) return;

		previouslyFocused = document.activeElement;
		password = "";
		passwordConfirmation = "";
		localError = "";
		tick().then(() => passwordInput?.focus());

		return () => {
			if (previouslyFocused?.isConnected) {
				previouslyFocused.focus();
			}
		};
	});

	function submit(event) {
		event.preventDefault();
		localError = "";

		if (password.length < 8) {
			localError = "Password must be at least 8 characters.";
			return;
		}

		if (password !== passwordConfirmation) {
			localError = "Passwords do not match.";
			return;
		}

		onconfirm({ password, password_confirmation: passwordConfirmation });
	}

	function handleKeydown(event) {
		if (!open) return;

		if (event.key === "Escape" && !busy) {
			event.preventDefault();
			oncancel();
			return;
		}

		if (event.key !== "Tab" || !dialogElement) return;

		const focusable = [
			...dialogElement.querySelectorAll(
				"input:not(:disabled), button:not(:disabled)",
			),
		];
		if (!focusable.length) return;

		const first = focusable[0];
		const last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}
</script>

<svelte:window onkeydown={handleKeydown} />

{#if open}
	<div class="dialog-layer" role="presentation">
		<button
			class="backdrop"
			type="button"
			aria-label="Close password reset"
			onclick={() => !busy && oncancel()}
		></button>

		<dialog
			class="dialog"
			open
			bind:this={dialogElement}
			aria-labelledby="reset-title"
			aria-describedby="reset-description"
		>
			<form onsubmit={submit}>
				<div class="key-icon" aria-hidden="true">•••</div>
				<div class="dialog-copy">
					<p class="eyebrow">ACCOUNT SECURITY</p>
					<h2 id="reset-title">Reset staff password</h2>
					<p id="reset-description">
						Set a new password for <strong>{staffName}</strong>.
						Their existing login sessions will be signed out.
					</p>

					<label>
						<span>New Password</span>
						<input
							bind:this={passwordInput}
							bind:value={password}
							type="password"
							autocomplete="new-password"
							minlength="8"
							disabled={busy}
							required
						/>
					</label>

					<label>
						<span>Confirm New Password</span>
						<input
							bind:value={passwordConfirmation}
							type="password"
							autocomplete="new-password"
							minlength="8"
							disabled={busy}
							required
						/>
					</label>

					<p class="hint">Use at least 8 characters.</p>

					{#if localError || errorMessage}
						<div class="dialog-error" role="alert">
							{localError || errorMessage}
						</div>
					{/if}
				</div>

				<footer>
					<button
						type="button"
						class="cancel"
						disabled={busy}
						onclick={oncancel}>Cancel</button
					>
					<button type="submit" class="primary" disabled={busy}>
						{busy ? "Resetting…" : "Reset password"}
					</button>
				</footer>
			</form>
		</dialog>
	</div>
{/if}

<style>
	.dialog-layer {
		position: fixed;
		inset: 0;
		z-index: 1000;
		display: grid;
		place-items: center;
		padding: 20px;
	}
	.backdrop {
		position: absolute;
		inset: 0;
		width: 100%;
		border: 0;
		background: rgb(15 23 42 / 52%);
		backdrop-filter: blur(3px);
	}
	.dialog {
		position: relative;
		width: min(460px, 100%);
		margin: 0;
		padding: 0;
		overflow: hidden;
		border: 1px solid #e2e7ee;
		border-radius: 14px;
		background: #fff;
		color: #172033;
		box-shadow: 0 28px 80px rgb(15 23 42 / 26%);
	}
	.key-icon {
		display: grid;
		width: 43px;
		height: 43px;
		margin: 27px 28px 0;
		place-items: center;
		border-radius: 11px;
		background: #edf2ff;
		color: #2855bd;
		font-size: 13px;
		font-weight: 900;
		letter-spacing: 1px;
	}
	.dialog-copy {
		padding: 17px 28px 25px;
	}
	.eyebrow {
		margin: 0 0 6px;
		color: #2855bd;
		font-size: 9px;
		font-weight: 800;
		letter-spacing: 0.13em;
	}
	h2 {
		margin: 0;
		font-size: 20px;
		letter-spacing: -0.025em;
	}
	#reset-description {
		margin: 9px 0 20px;
		color: #707c8f;
		font-size: 12px;
		line-height: 1.6;
	}
	#reset-description strong {
		color: #354156;
	}
	label {
		display: flex;
		flex-direction: column;
		gap: 7px;
		margin-top: 14px;
	}
	label span {
		color: #344054;
		font-size: 12px;
		font-weight: 700;
	}
	input {
		width: 100%;
		height: 42px;
		padding: 0 12px;
		border: 1px solid #d3dae5;
		border-radius: 8px;
		color: #172033;
		outline: none;
	}
	input:focus {
		border-color: #315ee7;
		box-shadow: 0 0 0 3px rgb(49 94 231 / 12%);
	}
	.hint {
		margin: 7px 0 0;
		color: #8a95a5;
		font-size: 10px;
	}
	.dialog-error {
		margin-top: 13px;
		padding: 10px 12px;
		border-radius: 8px;
		background: #fff0f0;
		color: #b83232;
		font-size: 11px;
	}
	footer {
		display: flex;
		justify-content: flex-end;
		gap: 9px;
		padding: 16px 28px;
		border-top: 1px solid #e8ecf1;
		background: #f8fafc;
	}
	footer button {
		display: inline-flex;
		min-width: 110px;
		height: 40px;
		align-items: center;
		justify-content: center;
		border-radius: 8px;
		font-size: 11px;
		font-weight: 750;
		cursor: pointer;
	}
	.cancel {
		border: 1px solid #d7dee8;
		background: #fff;
		color: #4d5a6e;
	}
	.primary {
		border: 1px solid #2554c7;
		background: #2554c7;
		color: #fff;
	}
	footer button:disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
	@media (max-width: 520px) {
		.dialog-layer {
			align-items: end;
			padding: 10px;
		}
		.dialog {
			border-radius: 14px 14px 10px 10px;
		}
		.key-icon {
			margin: 22px 22px 0;
		}
		.dialog-copy {
			padding: 15px 22px 22px;
		}
		footer {
			display: grid;
			grid-template-columns: 1fr 1fr;
			padding: 14px 22px 20px;
		}
		footer button {
			width: 100%;
			min-width: 0;
		}
	}
</style>
