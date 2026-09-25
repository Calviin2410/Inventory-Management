<script>
	import { tick } from "svelte";

	let {
		open = false,
		existingName = "",
		existingPhone = "",
		enteredName = "",
		oncancel = () => {},
		onconfirm = () => {},
	} = $props();

	let dialogElement = $state();
	let cancelButton = $state();
	let previouslyFocused;

	$effect(() => {
		if (!open) return;

		previouslyFocused = document.activeElement;
		tick().then(() => cancelButton?.focus());

		return () => {
			if (previouslyFocused?.isConnected) previouslyFocused.focus();
		};
	});

	function handleKeydown(event) {
		if (!open) return;

		if (event.key === "Escape") {
			event.preventDefault();
			oncancel();
			return;
		}

		if (event.key !== "Tab" || !dialogElement) return;

		const buttons = [...dialogElement.querySelectorAll("button")];
		const first = buttons[0];
		const last = buttons[buttons.length - 1];

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
			aria-label="Close customer match"
			onclick={oncancel}
		></button>
		<dialog
			class="dialog"
			open
			bind:this={dialogElement}
			aria-labelledby="match-title"
			aria-describedby="match-description"
		>
			<div class="match-icon" aria-hidden="true">?</div>
			<div class="dialog-copy">
				<p class="eyebrow">POSSIBLE EXISTING CUSTOMER</p>
				<h2 id="match-title">Use the existing customer?</h2>
				<p id="match-description">
					This phone number is already registered, but the entered
					name is different.
				</p>

				<div class="comparison">
					<div>
						<span>Existing customer</span><strong
							>{existingName || "-"}</strong
						>
					</div>
					<div>
						<span>Phone</span><strong>{existingPhone}</strong>
					</div>
					<div>
						<span>Entered name</span><strong
							>{enteredName || "No name entered"}</strong
						>
					</div>
				</div>

				<p class="note">
					No new customer will be created if you continue.
				</p>
			</div>

			<footer>
				<button
					class="cancel"
					type="button"
					onclick={oncancel}
					bind:this={cancelButton}>Go back</button
				>
				<button class="primary" type="button" onclick={onconfirm}
					>Use existing customer</button
				>
			</footer>
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
		width: min(470px, 100%);
		margin: 0;
		padding: 0;
		overflow: hidden;
		border: 1px solid #e2e7ee;
		border-radius: 14px;
		background: #fff;
		color: #172033;
		box-shadow: 0 28px 80px rgb(15 23 42 / 26%);
	}
	.match-icon {
		display: grid;
		width: 43px;
		height: 43px;
		margin: 27px 28px 0;
		place-items: center;
		border-radius: 11px;
		background: #fff8e8;
		color: #9a6816;
		font-size: 18px;
		font-weight: 800;
	}
	.dialog-copy {
		padding: 17px 28px 25px;
	}
	.eyebrow {
		margin: 0 0 6px;
		color: #9a6816;
		font-size: 9px;
		font-weight: 800;
		letter-spacing: 0.13em;
	}
	h2 {
		margin: 0;
		font-size: 20px;
		letter-spacing: -0.025em;
	}
	#match-description {
		margin: 9px 0 18px;
		color: #707c8f;
		font-size: 12px;
		line-height: 1.6;
	}
	.comparison {
		overflow: hidden;
		border: 1px solid #e4e8ef;
		border-radius: 9px;
	}
	.comparison div {
		display: grid;
		grid-template-columns: 125px minmax(0, 1fr);
		gap: 12px;
		padding: 11px 13px;
		border-bottom: 1px solid #edf0f4;
	}
	.comparison div:last-child {
		border-bottom: 0;
	}
	.comparison span {
		color: #8a95a5;
		font-size: 10px;
	}
	.comparison strong {
		overflow-wrap: anywhere;
		color: #29354a;
		font-size: 11px;
	}
	.note {
		margin: 12px 0 0;
		color: #7a8496;
		font-size: 10px;
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
		min-height: 40px;
		align-items: center;
		justify-content: center;
		padding: 0 15px;
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
	@media (max-width: 520px) {
		.dialog-layer {
			align-items: end;
			padding: 10px;
		}
		.dialog {
			border-radius: 14px 14px 10px 10px;
		}
		.comparison div {
			grid-template-columns: 1fr;
			gap: 3px;
		}
		footer {
			display: grid;
			grid-template-columns: 1fr;
			padding: 14px 22px 20px;
		}
		footer button {
			width: 100%;
		}
	}
</style>
