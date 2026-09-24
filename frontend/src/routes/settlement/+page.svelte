<script>
	import { onMount } from "svelte";

	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import Toast from "$lib/Toast.svelte";

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let selectedInvoice = $state(null);
	let selectedAction = $state("");
	let remark = $state("");
	let actionError = $state("");
	let submitting = $state(false);
	let successMessage = $state("");
	let fromDate = $state(malaysiaToday());
	let toDate = $state(malaysiaToday());
	let paidCount = $derived(invoices.filter((invoice) => invoice.status === "paid").length);
	let unpaidCount = $derived(invoices.filter((invoice) => invoice.status === "unpaid").length);

	function malaysiaToday() {
		const parts = new Intl.DateTimeFormat("en-GB", {
			timeZone: "Asia/Kuala_Lumpur",
			year: "numeric",
			month: "2-digit",
			day: "2-digit",
		}).formatToParts(new Date());
		const value = Object.fromEntries(parts.map((part) => [part.type, part.value]));
		return `${value.year}-${value.month}-${value.day}`;
	}

	function formatDateTime(value) {
		if (!value) return "—";

		return new Intl.DateTimeFormat("en-MY", {
			dateStyle: "medium",
			timeStyle: "short",
			timeZone: "Asia/Kuala_Lumpur",
		}).format(new Date(value));
	}

	async function loadSettlements() {
		loading = true;
		errorMessage = "";

		try {
			const result = await api.getSettlements({ from: fromDate, to: toDate });
			invoices = Array.isArray(result) ? result : [];
		} catch (error) {
			errorMessage = error?.message || "Unable to load today's invoices.";
		} finally {
			loading = false;
		}
	}

	function openDialog(invoice, action) {
		selectedInvoice = invoice;
		selectedAction = action;
		remark = "";
		actionError = "";
	}

	function closeDialog() {
		if (submitting) return;
		selectedInvoice = null;
		selectedAction = "";
		remark = "";
		actionError = "";
	}

	async function submitAction() {
		const cleanRemark = remark.trim();
		if (!cleanRemark) {
			actionError = "Remark is required.";
			return;
		}

		submitting = true;
		actionError = "";

		try {
			const updated = await api.updateSettlement(selectedInvoice.id, {
				action: selectedAction,
				remark: cleanRemark,
			});
			invoices = invoices.map((invoice) =>
				invoice.id === updated.id ? updated : invoice,
			);
			successMessage = selectedAction === "settle"
				? `${updated.invoice_no} settled successfully.`
				: `${updated.invoice_no} reopened successfully.`;
			closeDialog();
		} catch (error) {
			actionError = error?.errors?.remark?.[0]
				|| error?.errors?.action?.[0]
				|| error?.message
				|| "Unable to update settlement.";
		} finally {
			submitting = false;
			if (!actionError && selectedInvoice) closeDialog();
		}
	}

	onMount(loadSettlements);
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">DAILY CONTROL</p>
			<h1>Settlement</h1>
			<p>Review and settle invoices created today.</p>
		</div>
	</header>

	<div class="payment-summary" aria-label="Payment status summary">
		<div class="summary-card paid-summary">
			<span class="summary-icon" aria-hidden="true">✓</span>
			<div><strong>{paidCount}</strong><span>Paid Invoices</span></div>
		</div>
		<div class="summary-card unpaid-summary">
			<span class="summary-icon" aria-hidden="true">×</span>
			<div><strong>{unpaidCount}</strong><span>Unpaid Invoices</span></div>
		</div>
	</div>

	<form class="toolbar date-toolbar" onsubmit={(event) => { event.preventDefault(); loadSettlements(); }}>
		<label>From
			<input class="control" type="date" bind:value={fromDate} max={toDate} required />
		</label>
		<label>To
			<input class="control" type="date" bind:value={toDate} min={fromDate} required />
		</label>
		<button class="btn btn-primary" type="submit" disabled={loading || !fromDate || !toDate}>View</button>
		<button class="btn" type="button" onclick={() => { fromDate = malaysiaToday(); toDate = malaysiaToday(); loadSettlements(); }} disabled={loading}>Today</button>
		<button class="btn refresh-button" type="button" onclick={loadSettlements} disabled={loading}>Refresh</button>
	</form>

	{#if successMessage}
		<Toast message={successMessage} onclose={() => (successMessage = "")} />
	{/if}

	<section class="panel settlement-panel">
		{#if loading}
			<SkeletonTable rows={5} columns={6} />
		{:else if errorMessage}
			<div class="state error">{errorMessage}</div>
		{:else if invoices.length === 0}
			<div class="state">
				<h3>No invoices found</h3>
				<p>No invoices were created in the selected date range.</p>
			</div>
		{:else}
			<div class="table-card">
				<table class="data-table">
					<thead>
						<tr>
							<th>Invoice Number</th>
							<th>Created Date</th>
							<th>Payment Status</th>
							<th>Settlement Status</th>
							<th>Remark</th>
							<th class="action-column">Action</th>
						</tr>
					</thead>
					<tbody>
						{#each invoices as invoice (invoice.id)}
							<tr>
								<td class="strong">{invoice.invoice_no}</td>
								<td>{formatDateTime(invoice.created_at)}</td>
								<td>
									<span class:paid={invoice.status === "paid"} class="payment-badge">
										{invoice.status === "paid" ? "Paid" : "Unpaid"}
									</span>
								</td>
								<td>
									<span class:settled={invoice.settlement_status === "settled"} class="status-badge">
										<span class="status-icon" aria-hidden="true">{invoice.settlement_status === "settled" ? "✓" : "×"}</span>
										{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}
									</span>
								</td>
								<td class="remark-cell">{invoice.settlement_remark || "—"}</td>
								<td class="action-cell">
									<button class="table-action settle-action" type="button" disabled={invoice.settlement_status === "settled"} onclick={() => openDialog(invoice, "settle")}>Settle</button>
									<button class="table-action reopen-action" type="button" disabled={invoice.settlement_status !== "settled"} onclick={() => openDialog(invoice, "reopen")}>Reopen</button>
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		{/if}
	</section>
</main>

{#if selectedInvoice}
	<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && closeDialog()}>
		<form class="settlement-dialog" onsubmit={(event) => { event.preventDefault(); submitAction(); }}>
			<h2>{selectedAction === "settle" ? "Settle Invoice" : "Reopen Settlement"}</h2>
			<p>{selectedInvoice.invoice_no}</p>

			{#if actionError}<div class="dialog-error" role="alert">{actionError}</div>{/if}

			<label>
				Remark <span>*</span>
				<textarea bind:value={remark} maxlength="1000" rows="4" placeholder="Enter the reason or settlement note" required></textarea>
			</label>

			<div class="dialog-actions">
				<button class="btn" type="button" onclick={closeDialog} disabled={submitting}>Cancel</button>
				<button class="btn btn-primary" type="submit" disabled={submitting || !remark.trim()}>
					{submitting ? "Saving…" : selectedAction === "settle" ? "Settle" : "Reopen"}
				</button>
			</div>
		</form>
	</div>
{/if}

<style>
	.settlement-panel { overflow: hidden; }
	.payment-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 220px)); gap: 12px; margin-bottom: 14px; }
	.summary-card { display: flex; align-items: center; gap: 13px; padding: 16px 18px; border: 1px solid #e2e8f0; border-radius: 10px; background: white; box-shadow: 0 2px 8px rgb(15 23 42 / 3%); }
	.summary-icon { display: grid; width: 34px; height: 34px; place-items: center; flex: none; border-radius: 50%; color: white; font-size: 20px; font-weight: 800; }
	.paid-summary .summary-icon { background: #0a8f67; }
	.unpaid-summary .summary-icon { background: #d92d4c; }
	.summary-card div { display: grid; gap: 2px; }
	.summary-card strong { color: #172033; font-size: 22px; line-height: 1; }
	.summary-card div span { color: #64748b; font-size: 12px; font-weight: 650; }
	.date-toolbar { align-items: flex-end; margin-bottom: 20px; }
	.date-toolbar label { display: grid; gap: 6px; color: #536078; font-size: 11px; font-weight: 700; }
	.date-toolbar .control { min-width: 170px; }
	.refresh-button { margin-left: 0; }
	.state { padding: 28px; color: #64748b; text-align: center; }
	.state h3 { margin: 0 0 6px; color: #111827; }
	.state p { margin: 0; }
	.state.error { color: #b42318; }
	.status-badge { display: inline-flex; min-width: 92px; align-items: center; justify-content: center; gap: 6px; padding: 5px 10px; border-radius: 999px; background: #fff1f2; color: #be123c; font-size: 12px; font-weight: 700; }
	.status-badge.settled { background: #dff8ee; color: #087a55; }
	.status-icon { display: grid; width: 17px; height: 17px; place-items: center; border-radius: 50%; background: #be123c; color: white; font-size: 12px; line-height: 1; }
	.status-badge.settled .status-icon { background: #087a55; }
	.payment-badge { display: inline-flex; min-width: 66px; justify-content: center; padding: 5px 10px; border-radius: 999px; background: #fee2e2; color: #c81e3a; font-size: 12px; font-weight: 700; }
	.payment-badge.paid { background: #ccfbf1; color: #0f766e; }
	.remark-cell { max-width: 320px; color: #536078; line-height: 1.45; white-space: normal; }
	.action-column { width: 190px; text-align: right !important; }
	.action-cell { display: flex; justify-content: flex-end; gap: 7px; }
	.table-action { min-height: 34px; padding: 0 11px; border: 1px solid #d7deea; border-radius: 7px; background: white; color: #344054; font-size: 12px; font-weight: 700; cursor: pointer; }
	.settle-action:not(:disabled) { border-color: #9dddc9; color: #087a55; }
	.reopen-action:not(:disabled) { border-color: #f5b9c5; color: #be123c; }
	.table-action:disabled { opacity: .35; cursor: not-allowed; }
	.dialog-layer { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 20px; background: rgb(15 23 42 / 45%); }
	.settlement-dialog { width: min(100%, 440px); padding: 25px; border-radius: 12px; background: white; box-shadow: 0 24px 70px rgb(15 23 42 / 24%); }
	.settlement-dialog h2 { margin: 0; color: #172033; font-size: 21px; }
	.settlement-dialog > p { margin: 6px 0 22px; color: #64748b; }
	.settlement-dialog label { display: grid; gap: 8px; color: #344054; font-size: 13px; font-weight: 700; }
	.settlement-dialog label span { color: #dc2626; }
	textarea { box-sizing: border-box; width: 100%; resize: vertical; padding: 11px 12px; border: 1px solid #ccd4e0; border-radius: 9px; outline: none; font: inherit; font-weight: 400; line-height: 1.5; }
	textarea:focus { border-color: #315ee7; box-shadow: 0 0 0 3px rgb(49 94 231 / 12%); }
	.dialog-error { margin-bottom: 16px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; font-size: 12px; }
	.dialog-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 22px; }
	@media (max-width: 760px) { .payment-summary { grid-template-columns: 1fr 1fr; } .date-toolbar label, .date-toolbar .control { width: 100%; } .refresh-button { margin-left: 0; } .data-table { min-width: 980px; } .action-cell { display: table-cell; white-space: nowrap; } .table-action + .table-action { margin-left: 5px; } }
	@media (max-width: 480px) { .payment-summary { grid-template-columns: 1fr; } }
</style>
