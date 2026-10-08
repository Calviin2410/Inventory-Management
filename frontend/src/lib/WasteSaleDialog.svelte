<script>
	import { onMount } from "svelte";
	import DateInput from "$lib/DateInput.svelte";
	import { api } from "$lib/api.js";

	let { invoice, oncancel, onsaved } = $props();
	let amount = $state("");
	let remark = $state("");
	let receivedDate = $state("");
	let saving = $state(false);
	let errorMessage = $state("");

	onMount(() => {
		amount = invoice?.waste_sale_amount ?? "";
		remark = invoice?.waste_sale_remark ?? "";
		receivedDate = invoice?.waste_sale_recorded_at?.slice(0, 10)
			?? new Date(Date.now() - new Date().getTimezoneOffset() * 60_000)
				.toISOString()
				.slice(0, 10);
	});

	async function submit() {
		if (!invoice || saving) return;
		saving = true;
		errorMessage = "";
		try {
			const updated = await api.updateWasteSale(invoice.id, {
				amount: Number(amount),
				remark: remark.trim() || null,
				received_date: receivedDate,
			});
			onsaved?.(updated);
		} catch (error) {
			errorMessage = error?.errors?.amount?.[0]
				|| error?.errors?.received_date?.[0]
				|| error?.message
				|| "Unable to save waste sale.";
		} finally {
			saving = false;
		}
	}
</script>

<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && !saving && oncancel?.()}>
	<form class="sale-dialog" onsubmit={(event) => { event.preventDefault(); submit(); }}>
		<h2>{invoice?.waste_sale_amount === null ? "Record Waste Sale" : "Update Waste Sale"}</h2>
		<p>{invoice?.invoice_no}</p>
		{#if errorMessage}<div class="dialog-error" role="alert">{errorMessage}</div>{/if}
		<label>Amount (RM)<input type="number" min="0" max="9999999999.99" step="0.01" bind:value={amount} required /></label>
		<label>Received Date<DateInput bind:value={receivedDate} required ariaLabel="Waste sale received date" /></label>
		<label>Remark <small>Optional</small><textarea rows="4" maxlength="1000" bind:value={remark}></textarea></label>
		<div class="dialog-actions">
			<button class="btn" type="button" onclick={() => oncancel?.()} disabled={saving}>Cancel</button>
			<button class="btn btn-primary" type="submit" disabled={saving}>{saving ? "Saving…" : "Save Sale"}</button>
		</div>
	</form>
</div>

<style>
	.dialog-layer { position: fixed; inset: 0; z-index: 2100; display: grid; place-items: center; padding: 20px; background: rgb(15 23 42 / 48%); }
	.sale-dialog { width: min(100%, 440px); padding: 24px; border-radius: 12px; background: white; box-shadow: 0 24px 70px rgb(15 23 42 / 24%); }
	.sale-dialog h2 { margin: 0 0 6px; color: #172033; }
	.sale-dialog > p { margin: 0 0 18px; color: #64748b; }
	.sale-dialog label { display: grid; gap: 7px; margin-top: 15px; color: #344054; font-size: 13px; font-weight: 700; }
	.sale-dialog label small { color: #8a94a6; font-weight: 500; }
	.sale-dialog input, .sale-dialog textarea { width: 100%; padding: 11px 12px; border: 1px solid #ccd4e0; border-radius: 9px; outline: none; background: white; font: inherit; font-weight: 400; }
	.sale-dialog textarea { resize: vertical; }
	.sale-dialog input:focus, .sale-dialog textarea:focus { border-color: #315ee7; box-shadow: 0 0 0 3px rgb(49 94 231 / 12%); }
	.dialog-error { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; }
	.dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
	@media (max-width: 560px) { .dialog-actions { flex-direction: column-reverse; } .dialog-actions .btn { width: 100%; } }
</style>
