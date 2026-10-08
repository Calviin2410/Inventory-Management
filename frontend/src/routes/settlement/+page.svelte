<script>
	import { onMount } from "svelte";

	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import Toast from "$lib/Toast.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import { openInvoicePrintWindow } from "$lib/invoicePrint.js";

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let selectedInvoice = $state(null);
	let selectedAction = $state("");
	let remark = $state("");
	let actionError = $state("");
	let submitting = $state(false);
	let exportingId = $state(null);
	let openActionId = $state(null);
	let actionMenuPosition = $state({ top: 0, left: 0 });
	let actionInvoice = $derived(invoices.find((invoice) => invoice.id === openActionId) ?? null);
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
		openActionId = null;
		selectedInvoice = invoice;
		selectedAction = action;
		remark = "";
		actionError = "";
	}

	function toggleActionMenu(invoiceId, event) {
		if (openActionId === invoiceId) {
			openActionId = null;
			return;
		}

		const rect = event.currentTarget.getBoundingClientRect();
		const menuWidth = 180;
		const menuHeight = 132;
		const gap = 6;
		const padding = 10;
		actionMenuPosition = {
			top: Math.min(rect.bottom + gap, window.innerHeight - menuHeight - padding),
			left: Math.max(padding, rect.right - menuWidth),
		};
		openActionId = invoiceId;
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

	async function exportSettledInvoice(invoice) {
		if (invoice.settlement_status !== "settled" || exportingId !== null) return;
		openActionId = null;

		const printWindow = window.open("", "_blank");
		if (!printWindow) {
			errorMessage = "Please allow pop-ups to export this invoice.";
			return;
		}

		printWindow.document.write('<p style="font: 14px sans-serif; padding: 24px;">Preparing settled invoice...</p>');
		exportingId = invoice.id;
		errorMessage = "";

		try {
			const fullInvoice = await api.getInvoice(invoice.id);
			if (fullInvoice.settlement_status !== "settled") {
				throw new Error("Only settled invoices can be exported with the Settled stamp.");
			}
			openInvoicePrintWindow(printWindow, fullInvoice, { settledStamp: true });
		} catch (error) {
			printWindow.close();
			errorMessage = error?.message || "Unable to export settled invoice.";
		} finally {
			exportingId = null;
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
			<DateInput bind:value={fromDate} max={toDate} required ariaLabel="Select from date" />
		</label>
		<label>To
			<DateInput bind:value={toDate} min={fromDate} required ariaLabel="Select to date" />
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
			<SkeletonTable rows={5} columns={7} />
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
							<th>Settlement Date</th>
							<th class="action-column">Action</th>
						</tr>
					</thead>
					<tbody>
						{#each invoices as invoice (invoice.id)}
							<tr>
								<td><a class="invoice-link" href={`/invoices/${invoice.id}`}>{invoice.invoice_no}</a></td>
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
								<td class="settlement-date">{formatDateTime(invoice.settled_at)}</td>
								<td class="action-cell">
									<button class="action-button" type="button" aria-label={`Actions for ${invoice.invoice_no}`} aria-expanded={openActionId === invoice.id} onclick={(event) => toggleActionMenu(invoice.id, event)}>Action <span aria-hidden="true">⌄</span></button>
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		{/if}
	</section>

	{#if actionInvoice}
		<div class="action-dropdown" style:--menu-top={`${actionMenuPosition.top}px`} style:--menu-left={`${actionMenuPosition.left}px`}>
			<button type="button" disabled={actionInvoice.settlement_status !== "settled" || exportingId !== null} onclick={() => exportSettledInvoice(actionInvoice)}>{exportingId === actionInvoice.id ? "Preparing…" : "Export Invoice"}</button>
			<button type="button" disabled={actionInvoice.settlement_status === "settled"} onclick={() => openDialog(actionInvoice, "settle")}>Settle</button>
			<button class="reopen-option" type="button" disabled={actionInvoice.settlement_status !== "settled"} onclick={() => openDialog(actionInvoice, "reopen")}>Reopen</button>
		</div>
	{/if}
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
	.date-toolbar label { display: grid; min-width: 0; flex: 1 1 190px; gap: 6px; color: #536078; font-size: 11px; font-weight: 700; }
	.date-toolbar .btn { flex: 0 1 auto; }
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
	.invoice-link { color: #172033; font-weight: 700; text-decoration: none; }
	.invoice-link:hover { color: #2554c7; text-decoration: underline; }
	.settlement-date { white-space: nowrap; }
	.action-column { width: 120px; text-align: right !important; }
	.action-cell { text-align: right; }
	.action-button { display: inline-flex; min-height: 36px; align-items: center; justify-content: center; gap: 12px; padding: 0 13px; border: 1px solid #ccd4e0; border-radius: 7px; background: white; color: #172033; font-size: 12px; font-weight: 700; cursor: pointer; }
	.action-dropdown { position: fixed; top: var(--menu-top); left: var(--menu-left); z-index: 120; display: grid; width: 180px; overflow: hidden; padding: 6px; border: 1px solid #d7deea; border-radius: 9px; background: white; box-shadow: 0 12px 30px rgb(15 23 42 / 16%); }
	.action-dropdown button { min-height: 38px; padding: 0 11px; border: 0; border-radius: 6px; background: white; color: #344054; font-size: 12px; font-weight: 700; text-align: left; cursor: pointer; }
	.action-dropdown button:hover:not(:disabled) { background: #f3f6fb; color: #2554c7; }
	.action-dropdown .reopen-option:not(:disabled) { color: #be123c; }
	.action-dropdown button:disabled { opacity: .35; cursor: not-allowed; }
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
	@media (max-width: 900px) { .payment-summary { grid-template-columns: 1fr 1fr; } .date-toolbar label { width: 100%; } .refresh-button { margin-left: 0; } .data-table { min-width: 1120px; } .action-cell { white-space: nowrap; } }
	@media (max-width: 480px) { .payment-summary { grid-template-columns: 1fr; } }
</style>
