<script>
	import { tick } from "svelte";

	let {
		open = false,
		title = "Delete this record?",
		message = "This action cannot be undone.",
		itemName = "",
		confirmLabel = "Delete",
		busy = false,
		errorMessage = "",
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
		if (event.key === "Escape" && !busy) {
			event.preventDefault();
			oncancel();
			return;
		}
		if (event.key !== "Tab" || !dialogElement) return;
		const focusable = [...dialogElement.querySelectorAll("button:not(:disabled)")];
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
		<button class="backdrop" type="button" aria-label="Close confirmation" onclick={() => !busy && oncancel()}></button>
		<dialog class="dialog" open bind:this={dialogElement} aria-labelledby="confirm-title" aria-describedby="confirm-description">
			<div class="warning-icon" aria-hidden="true">!</div>
			<div class="dialog-copy">
				<p class="eyebrow">CONFIRM DELETION</p>
				<h2 id="confirm-title">{title}</h2>
				<p id="confirm-description">{message}</p>
				{#if itemName}<div class="record"><span>Selected record</span><strong>{itemName}</strong></div>{/if}
				<div class="warning"><span>i</span><p>This action is permanent and the deleted data cannot be recovered.</p></div>
				{#if errorMessage}<div class="dialog-error" role="alert">{errorMessage}</div>{/if}
			</div>
			<footer>
				<button class="cancel" type="button" disabled={busy} onclick={oncancel} bind:this={cancelButton}>Cancel</button>
				<button class="danger" type="button" disabled={busy} onclick={onconfirm}>
					{#if busy}<span class="spinner"></span>Deleting…{:else}{confirmLabel}{/if}
				</button>
			</footer>
		</dialog>
	</div>
{/if}

<style>
	.dialog-layer { position: fixed; inset: 0; z-index: 1000; display: grid; place-items: center; padding: 20px; }
	.backdrop { position: absolute; inset: 0; width: 100%; border: 0; background: rgb(15 23 42 / 52%); backdrop-filter: blur(3px); cursor: default; animation: fade-in .16s ease-out; }
	.dialog { position: relative; width: min(440px, 100%); margin: 0; padding: 0; overflow: hidden; border: 1px solid #e2e7ee; border-radius: 14px; background: #fff; color: inherit; box-shadow: 0 28px 80px rgb(15 23 42 / 26%); animation: enter .18s ease-out; }
	.warning-icon { display: grid; width: 43px; height: 43px; margin: 27px 28px 0; place-items: center; border-radius: 11px; background: #fff0f0; color: #c93838; font-size: 19px; font-weight: 800; box-shadow: inset 0 0 0 1px #ffdada; }
	.dialog-copy { padding: 17px 28px 25px; }
	.eyebrow { margin: 0 0 6px; color: #bf3f3f; font-size: 9px; font-weight: 800; letter-spacing: .13em; }
	h2 { margin: 0; color: #192337; font-size: 20px; letter-spacing: -.025em; }
	.dialog-copy > p:not(.eyebrow) { margin: 9px 0 0; color: #707c8f; font-size: 12px; line-height: 1.6; }
	.record { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-top: 20px; padding: 11px 13px; border: 1px solid #e4e8ef; border-radius: 8px; background: #f8fafc; }
	.record span { color: #8a95a5; font-size: 10px; }
	.record strong { overflow: hidden; color: #29354a; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
	.warning { display: flex; gap: 9px; margin-top: 13px; padding: 10px 12px; border-radius: 8px; background: #fff8e8; color: #8c641b; }
	.warning > span { display: grid; width: 17px; height: 17px; place-items: center; flex: none; border: 1px solid #d8aa4d; border-radius: 50%; font-size: 9px; font-weight: 800; }
	.warning p { margin: 0; font-size: 10px; line-height: 1.5; }
	.dialog-error { margin-top: 13px; padding: 10px 12px; border-radius: 8px; background: #fff0f0; color: #b83232; font-size: 10px; }
	footer { display: flex; justify-content: flex-end; gap: 9px; padding: 16px 28px; border-top: 1px solid #e8ecf1; background: #f8fafc; }
	footer button { display: inline-flex; min-width: 96px; height: 39px; align-items: center; justify-content: center; gap: 7px; border-radius: 8px; font-size: 11px; font-weight: 750; cursor: pointer; }
	.cancel { border: 1px solid #d7dee8; background: #fff; color: #4d5a6e; }
	.cancel:hover:not(:disabled) { background: #f4f6f9; }
	.danger { border: 1px solid #bc3030; background: linear-gradient(#d34a4a, #bd3434); color: #fff; box-shadow: 0 5px 12px rgb(190 52 52 / 20%); }
	.danger:hover:not(:disabled) { background: #b92e2e; }
	footer button:disabled { opacity: .6; cursor: not-allowed; }
	.spinner { width: 12px; height: 12px; border: 2px solid rgb(255 255 255 / 40%); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
	@keyframes spin { to { transform: rotate(360deg); } }
	@keyframes fade-in { from { opacity: 0; } }
	@keyframes enter { from { opacity: 0; transform: translateY(8px) scale(.98); } }
	@media (max-width: 520px) {
		.dialog-layer { align-items: end; padding: 10px; }
		.dialog { border-radius: 14px 14px 10px 10px; }
		.warning-icon { margin: 23px 22px 0; }
		.dialog-copy { padding: 15px 22px 22px; }
		footer { display: grid; grid-template-columns: 1fr 1fr; padding: 14px 22px 20px; }
		footer button { width: 100%; }
	}
</style>
