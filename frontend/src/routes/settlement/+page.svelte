<script>
	import { onMount } from "svelte";

	import { api } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import Toast from "$lib/Toast.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import { openInvoicePrintWindow } from "$lib/invoicePrint.js";
	import ColumnPicker from "$lib/ColumnPicker.svelte";
	import FilterSelect from "$lib/FilterSelect.svelte";

	const settlementColumns = [
		{ key: "invoiceNumber", label: "Invoice Number" },
		{ key: "createdDate", label: "Created Date" },
		{ key: "paymentStatus", label: "Payment Status" },
		{ key: "settlementStatus", label: "Settlement Status" },
		{ key: "remark", label: "Remark" },
		{ key: "settlementDate", label: "Settlement Date" },
	];
	let visibleColumns = $state(
		Object.fromEntries(
			settlementColumns.map((column) => [column.key, true]),
		),
	);

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
	let actionInvoice = $derived(
		invoices.find((invoice) => invoice.id === openActionId) ?? null,
	);
	let statusMenu = $state({ invoiceId: null, kind: null, top: 0, left: 0 });
	let statusMenuInvoice = $derived(
		invoices.find((invoice) => invoice.id === statusMenu.invoiceId) ?? null,
	);
	let successMessage = $state("");
	let isAdmin = $derived($user?.role === "admin");
	let paymentInvoice = $state(null);
	let paymentMethod = $state("cash");
	let paymentDate = $state("");
	let paymentError = $state("");
	let paymentSaving = $state(false);
	let unpaidInvoice = $state(null);
	let unpaidRemark = $state("");
	let unpaidError = $state("");
	let unpaidSaving = $state(false);
	let fromDate = $state(malaysiaToday());
	let toDate = $state(malaysiaToday());
	let search = $state("");
	let searchBy = $state("all");
	let settlementStatusFilter = $state("");
	let paidCount = $derived(
		invoices.filter((invoice) => invoice.status === "paid").length,
	);
	let unpaidCount = $derived(
		invoices.filter((invoice) => invoice.status === "unpaid").length,
	);
	let invoiceSortDirection = $state("");
	let dateSortDirection = $state("");

	function malaysiaToday() {
		const parts = new Intl.DateTimeFormat("en-GB", {
			timeZone: "Asia/Kuala_Lumpur",
			year: "numeric",
			month: "2-digit",
			day: "2-digit",
		}).formatToParts(new Date());
		const value = Object.fromEntries(
			parts.map((part) => [part.type, part.value]),
		);
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
			const params = { from: fromDate, to: toDate };
			if (search.trim()) {
				params.search = search.trim();
				if (searchBy !== "all") params.search_by = searchBy;
			}
			if (settlementStatusFilter)
				params.settlement_status = settlementStatusFilter;
			if (invoiceSortDirection) {
				params.sort = "invoice_no";
				params.direction = invoiceSortDirection;
			} else if (dateSortDirection) {
				params.sort = "issued_date";
				params.direction = dateSortDirection;
			}
			const result = await api.getSettlements(params);
			invoices = Array.isArray(result) ? result : [];
		} catch (error) {
			errorMessage = error?.message || "Unable to load today's invoices.";
		} finally {
			loading = false;
		}
	}

	function toggleSort(column) {
		if (column === "invoice_no") {
			invoiceSortDirection =
				invoiceSortDirection === "asc" ? "desc" : "asc";
			dateSortDirection = "";
		} else {
			dateSortDirection = dateSortDirection === "asc" ? "desc" : "asc";
			invoiceSortDirection = "";
		}
		loadSettlements();
	}

	function openDialog(invoice, action) {
		openActionId = null;
		statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
		selectedInvoice = invoice;
		selectedAction = action;
		remark = "";
		actionError = "";
	}

	function canManageSettlement(invoice) {
		return (
			$user?.role === "admin" ||
			Number(invoice?.user_id) === Number($user?.id)
		);
	}

	function canSettleInvoice(invoice) {
		return invoice?.status === "paid" && invoice?.waste_sell_amount != null && invoice?.settlement_status !== "settled";
	}

	function settleRequirement(invoice) {
		if (invoice?.settlement_status === "settled") return "Already settled.";
		if (invoice?.status !== "paid") return "Mark this invoice Paid first.";
		if (invoice?.waste_sell_amount == null) return "Record Sell Waste first.";
		return "Ready to settle.";
	}

	function openPaymentDialog(invoice) {
		paymentInvoice = invoice;
		paymentMethod = "cash";
		paymentDate = malaysiaToday();
		paymentError = "";
		openActionId = null;
		statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
	}

	function closePaymentDialog() {
		if (!paymentSaving) paymentInvoice = null;
	}

	async function markInvoicePaid() {
		if (!paymentInvoice || !paymentMethod || !paymentDate || paymentSaving)
			return;
		paymentSaving = true;
		paymentError = "";
		try {
			const updated = await api.updateInvoice(paymentInvoice.id, {
				status: "paid",
				payment_method: paymentMethod,
				payment_date: paymentDate,
			});
			invoices = invoices.map((invoice) =>
				invoice.id === updated.id ? updated : invoice,
			);
			successMessage = `${updated.invoice_no} marked as paid.`;
			paymentInvoice = null;
		} catch (error) {
			paymentError = error?.message || "Unable to record payment.";
		} finally {
			paymentSaving = false;
		}
	}

	function openUnpaidDialog(invoice) {
		unpaidInvoice = invoice;
		unpaidRemark = "";
		unpaidError = "";
		openActionId = null;
		statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
	}

	function closeUnpaidDialog() {
		if (!unpaidSaving) unpaidInvoice = null;
	}

	async function markInvoiceUnpaid() {
		if (!unpaidInvoice || !unpaidRemark.trim() || unpaidSaving) return;
		unpaidSaving = true;
		unpaidError = "";
		try {
			const updated = await api.updateInvoice(unpaidInvoice.id, {
				status: "unpaid",
				unpaid_remark: unpaidRemark.trim(),
			});
			invoices = invoices.map((invoice) =>
				invoice.id === updated.id ? updated : invoice,
			);
			successMessage = `${updated.invoice_no} marked as unpaid.`;
			unpaidInvoice = null;
		} catch (error) {
			unpaidError =
				error?.errors?.unpaid_remark?.[0] ||
				error?.message ||
				"Unable to set invoice as unpaid.";
		} finally {
			unpaidSaving = false;
		}
	}

	function toggleActionMenu(invoiceId, event) {
		statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
		if (openActionId === invoiceId) {
			openActionId = null;
			return;
		}

		const rect = event.currentTarget.getBoundingClientRect();
		const menuWidth = 180;
		const menuHeight = 190;
		const gap = 6;
		const padding = 10;
		actionMenuPosition = {
			top: Math.max(
				padding,
				Math.min(
					rect.bottom + gap,
					window.innerHeight - menuHeight - padding,
				),
			),
			left: Math.max(padding, rect.right - menuWidth),
		};
		openActionId = invoiceId;
	}

	function toggleStatusMenu(invoice, kind, event) {
		const rect = event.currentTarget.getBoundingClientRect();
		const menuWidth = 150;
		const menuHeight = 100;
		const padding = 10;
		if (statusMenu.invoiceId === invoice.id && statusMenu.kind === kind) {
			statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
			return;
		}
		openActionId = null;
		statusMenu = {
			invoiceId: invoice.id,
			kind,
			top: Math.max(
				padding,
				Math.min(
					rect.bottom + 5,
					window.innerHeight - menuHeight - padding,
				),
			),
			left: Math.max(
				padding,
				Math.min(rect.left, window.innerWidth - menuWidth - padding),
			),
		};
	}

	function closeStatusMenu() {
		statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
	}

	function closeActionMenuOnOutsideClick(event) {
		const target = event.target;
		if (
			target instanceof Element &&
			target.closest(
				".more-button, .action-dropdown, .status-action, .status-dropdown",
			)
		) {
			return;
		}
		openActionId = null;
		closeStatusMenu();
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
			successMessage =
				selectedAction === "settle"
					? `${updated.invoice_no} settled successfully.`
					: `${updated.invoice_no} reopened successfully.`;
			closeDialog();
		} catch (error) {
			actionError =
				error?.errors?.remark?.[0] ||
				error?.errors?.action?.[0] ||
				error?.message ||
				"Unable to update settlement.";
		} finally {
			submitting = false;
			if (!actionError && selectedInvoice) closeDialog();
		}
	}

	async function exportSettledInvoice(invoice) {
		if (!isAdmin || invoice.settlement_status !== "settled" || exportingId !== null)
			return;
		openActionId = null;

		const printWindow = window.open("", "_blank");
		if (!printWindow) {
			errorMessage = "Please allow pop-ups to export this invoice.";
			return;
		}

		printWindow.document.write(
			'<p style="font: 14px sans-serif; padding: 24px;">Preparing settled invoice...</p>',
		);
		exportingId = invoice.id;
		errorMessage = "";

		try {
			const fullInvoice = await api.getInvoice(invoice.id);
			if (fullInvoice.settlement_status !== "settled") {
				throw new Error(
					"Only settled invoices can be exported with the Settled stamp.",
				);
			}
			openInvoicePrintWindow(printWindow, fullInvoice, {
				settledStamp: true,
			});
		} catch (error) {
			printWindow.close();
			errorMessage =
				error?.message || "Unable to export settled invoice.";
		} finally {
			exportingId = null;
		}
	}

	onMount(loadSettlements);
</script>

<svelte:window onclick={closeActionMenuOnOutsideClick} />

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
			<div>
				<strong>{unpaidCount}</strong><span>Unpaid Invoices</span>
			</div>
		</div>
	</div>

	<form
		class="toolbar settlement-search-toolbar"
		onsubmit={(event) => {
			event.preventDefault();
			loadSettlements();
		}}
	>
		<FilterSelect
			compact
			className="settlement-search-scope"
			ariaLabel="Search field"
			bind:value={searchBy}
			options={[
				{ value: "all", label: "All fields" },
				{ value: "invoice", label: "Invoice" },
				{ value: "customer", label: "Customer" },
				{ value: "barrel", label: "Barrel" },
			]}
		/>
		<div class="search-field">
			<input
				class="control"
				type="search"
				placeholder={searchBy === "invoice"
					? "Invoice number..."
					: searchBy === "customer"
						? "Customer name..."
						: searchBy === "barrel"
							? "Barrel code..."
							: "Invoice, customer or barrel..."}
				bind:value={search}
			/>
			{#if search}<button
					type="button"
					class="search-clear"
					aria-label="Clear search"
					onclick={() => {
						search = "";
						loadSettlements();
					}}>×</button
				>{/if}
		</div>
		<button class="btn btn-primary" type="submit" disabled={loading}
			>Search</button
		>
		<FilterSelect
			compact
			className="settlement-status-filter"
			ariaLabel="Filter settlement status"
			bind:value={settlementStatusFilter}
			onchange={loadSettlements}
			options={[
				{ value: "", label: "All Status" },
				{ value: "settled", label: "Settled" },
				{ value: "unsettled", label: "Unsettled" },
			]}
		/>
	</form>

	<section class="panel settlement-panel">
		<div
			class="settlement-date-range"
			aria-label="Filter settlements by date range"
		>
			<label class="settlement-date-filter">
				<span>From date</span>
				<DateInput
					bind:value={fromDate}
					max={toDate}
					onchange={loadSettlements}
					ariaLabel="Select from date"
				/>
			</label>
			<label class="settlement-date-filter">
				<span>To date</span>
				<DateInput
					bind:value={toDate}
					min={fromDate}
					onchange={loadSettlements}
					ariaLabel="Select to date"
				/>
			</label>
			<ColumnPicker
				columns={settlementColumns}
				bind:visible={visibleColumns}
			/>
		</div>

		{#if successMessage}
			<Toast
				message={successMessage}
				onclose={() => (successMessage = "")}
			/>
		{/if}

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
							{#if visibleColumns.invoiceNumber}<th
									aria-sort={invoiceSortDirection === "asc"
										? "ascending"
										: invoiceSortDirection === "desc"
											? "descending"
											: "none"}
									><button
										class="sort-header"
										type="button"
										onclick={() => toggleSort("invoice_no")}
										>Invoice Number <span aria-hidden="true"
											>{invoiceSortDirection === "asc"
												? "↑"
												: invoiceSortDirection ===
													  "desc"
													? "↓"
													: "↕"}</span
										></button
									></th
								>{/if}
							{#if visibleColumns.createdDate}<th
									aria-sort={dateSortDirection === "asc"
										? "ascending"
										: dateSortDirection === "desc"
											? "descending"
											: "none"}
									><button
										class="sort-header"
										type="button"
										onclick={() =>
											toggleSort("issued_date")}
										>Created Date <span aria-hidden="true"
											>{dateSortDirection === "asc"
												? "↑"
												: dateSortDirection === "desc"
													? "↓"
													: "↕"}</span
										></button
									></th
								>{/if}
							{#if visibleColumns.paymentStatus}<th
									>Payment Status</th
								>{/if}
							{#if visibleColumns.settlementStatus}<th
									>Settlement Status</th
								>{/if}
							{#if visibleColumns.remark}<th>Remark</th>{/if}
							{#if visibleColumns.settlementDate}<th
									>Settlement Date</th
								>{/if}
							<th class="action-column">Action</th>
						</tr>
					</thead>
					<tbody>
						{#each invoices as invoice (invoice.id)}
							<tr>
								{#if visibleColumns.invoiceNumber}<td
										><a
											class="invoice-link"
											href={`/invoices/${invoice.id}?source=settlement`}
											>{invoice.invoice_no}</a
										></td
									>{/if}
								{#if visibleColumns.createdDate}<td
										>{formatDateTime(
											invoice.created_at,
										)}</td
									>{/if}
								{#if visibleColumns.paymentStatus}<td>
						{#if canManageSettlement(invoice) && invoice.status !== "cancelled" && invoice.settlement_status !== "settled"}
											<button
												class:paid={invoice.status ===
													"paid"}
												class="payment-badge status-action"
												type="button"
												aria-expanded={statusMenu.invoiceId ===
													invoice.id &&
													statusMenu.kind ===
														"payment"}
												onclick={(event) =>
													toggleStatusMenu(
														invoice,
														"payment",
														event,
													)}
											>
												{invoice.status === "paid"
													? "Paid"
													: "Unpaid"}
												<span
													class="status-chevron"
													aria-hidden="true"
												></span>
											</button>
										{:else}
											<span
												class:paid={invoice.status ===
													"paid"}
												class="payment-badge"
												>{invoice.status === "paid"
													? "Paid"
													: "Unpaid"}</span
											>
										{/if}
									</td>{/if}
								{#if visibleColumns.settlementStatus}<td>
										{#if canManageSettlement(invoice)}
											<button
												class:settled={invoice.settlement_status ===
													"settled"}
												class="status-badge status-action"
												type="button"
												aria-expanded={statusMenu.invoiceId ===
													invoice.id &&
													statusMenu.kind ===
														"settlement"}
												onclick={(event) =>
													toggleStatusMenu(
														invoice,
														"settlement",
														event,
													)}
											>
												<span
													class="status-icon"
													aria-hidden="true"
													>{invoice.settlement_status ===
													"settled"
														? "✓"
														: "×"}</span
												>
												{invoice.settlement_status ===
												"settled"
													? "Settled"
													: "Unsettled"}
												<span
													class="status-chevron"
													aria-hidden="true"
												></span>
											</button>
										{:else}
											<span
												class:settled={invoice.settlement_status ===
													"settled"}
												class="status-badge"
											>
												<span
													class="status-icon"
													aria-hidden="true"
													>{invoice.settlement_status ===
													"settled"
														? "✓"
														: "×"}</span
												>
												{invoice.settlement_status ===
												"settled"
													? "Settled"
													: "Unsettled"}
											</span>
										{/if}
									</td>{/if}
								{#if visibleColumns.remark}<td
										class="remark-cell"
										>{invoice.settlement_remark || "—"}</td
									>{/if}
								{#if visibleColumns.settlementDate}<td
										class="settlement-date"
										>{formatDateTime(
											invoice.settled_at,
										)}</td
									>{/if}
								<td class="action-cell">
									<button
										class="more-button"
										type="button"
										aria-label={`Actions for ${invoice.invoice_no}`}
										aria-expanded={openActionId ===
											invoice.id}
										onclick={(event) =>
											toggleActionMenu(invoice.id, event)}
										>⋯</button
									>
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		{/if}
	</section>

	{#if actionInvoice}
		<div
			class="action-dropdown"
			style:--menu-top={`${actionMenuPosition.top}px`}
			style:--menu-left={`${actionMenuPosition.left}px`}
		>
			<a href={`/invoices/${actionInvoice.id}?source=settlement`}
				>View Invoice</a
			>
			<div class="menu-divider"></div>
			{#if isAdmin}
				<button
					type="button"
					disabled={actionInvoice.settlement_status !== "settled" ||
						exportingId !== null}
					onclick={() => exportSettledInvoice(actionInvoice)}
					>{exportingId === actionInvoice.id
						? "Preparing…"
						: "Export Invoice"}</button
				>
			{/if}
			{#if canManageSettlement(actionInvoice)}
				<div class="menu-divider"></div>
				{#if actionInvoice.settlement_status !== "settled" && !canSettleInvoice(actionInvoice)}
					<p class="settlement-requirement">{settleRequirement(actionInvoice)}</p>
				{/if}
				<button
					type="button"
					disabled={!canSettleInvoice(actionInvoice)}
					onclick={() => openDialog(actionInvoice, "settle")}
					>Settle</button
				>
				<button
					class="reopen-option"
					type="button"
					disabled={actionInvoice.settlement_status !== "settled"}
					onclick={() => openDialog(actionInvoice, "reopen")}
					>Reopen</button
				>
			{/if}
		</div>
	{/if}
	{#if statusMenuInvoice}
		<div
			class="status-dropdown"
			style:--menu-top={`${statusMenu.top}px`}
			style:--menu-left={`${statusMenu.left}px`}
		>
			{#if statusMenu.kind === "payment"}
				<button
					type="button"
					disabled={statusMenuInvoice.status === "paid"}
					onclick={() => openPaymentDialog(statusMenuInvoice)}
					>Paid</button
				>
				<button
					type="button"
					class="unpaid-option"
					disabled={statusMenuInvoice.status !== "paid"}
					onclick={() => openUnpaidDialog(statusMenuInvoice)}
					>Unpaid</button
				>
			{:else}
				{#if statusMenuInvoice.settlement_status !== "settled" && !canSettleInvoice(statusMenuInvoice)}
					<p class="settlement-requirement">{settleRequirement(statusMenuInvoice)}</p>
				{/if}
				<button
					type="button"
					disabled={!canSettleInvoice(statusMenuInvoice)}
					onclick={() => openDialog(statusMenuInvoice, "settle")}
					>Settle</button
				>
				<button
					type="button"
					class="unpaid-option"
					disabled={statusMenuInvoice.settlement_status !== "settled"}
					onclick={() => openDialog(statusMenuInvoice, "reopen")}
					>Reopen</button
				>
			{/if}
		</div>
	{/if}
</main>

{#if selectedInvoice}
	<div
		class="dialog-layer"
		role="presentation"
		onclick={(event) =>
			event.currentTarget === event.target && closeDialog()}
	>
		<form
			class="settlement-dialog"
			onsubmit={(event) => {
				event.preventDefault();
				submitAction();
			}}
		>
			<h2>{selectedAction === "settle" ? "Settle Invoice" : "Reopen"}</h2>
			<p>{selectedInvoice.invoice_no}</p>

			{#if actionError}<div class="dialog-error" role="alert">
					{actionError}
				</div>{/if}

			<label>
				Remark <span>*</span>
				<textarea
					bind:value={remark}
					maxlength="1000"
					rows="4"
					placeholder="Enter the reason or settlement note"
					required></textarea>
			</label>

			<div class="dialog-actions">
				<button
					class="btn"
					type="button"
					onclick={closeDialog}
					disabled={submitting}>Cancel</button
				>
				<button
					class="btn btn-primary"
					type="submit"
					disabled={submitting || !remark.trim()}
				>
					{submitting
						? "Saving…"
						: selectedAction === "settle"
							? "Settle"
							: "Reopen"}
				</button>
			</div>
		</form>
	</div>
{/if}

{#if paymentInvoice}
	<div
		class="dialog-layer"
		role="presentation"
		onclick={(event) =>
			event.currentTarget === event.target && closePaymentDialog()}
	>
		<form
			class="settlement-dialog"
			onsubmit={(event) => {
				event.preventDefault();
				markInvoicePaid();
			}}
		>
			<h2>Record Payment</h2>
			<p>Mark {paymentInvoice.invoice_no} as paid.</p>
			{#if paymentError}<div class="dialog-error" role="alert">
					{paymentError}
				</div>{/if}
			<label
				>Payment Method
				<select bind:value={paymentMethod} required>
					<option value="cash">Cash</option>
					<option value="bank_in">Bank In</option>
				</select>
			</label>
			<label
				>Payment Date
				<DateInput
					bind:value={paymentDate}
					required
					ariaLabel="Select payment date"
				/>
			</label>
			<div class="dialog-actions">
				<button
					class="btn"
					type="button"
					onclick={closePaymentDialog}
					disabled={paymentSaving}>Cancel</button
				>
				<button
					class="btn btn-primary"
					type="submit"
					disabled={paymentSaving || !paymentDate}
					>{paymentSaving ? "Saving…" : "Confirm Paid"}</button
				>
			</div>
		</form>
	</div>
{/if}

{#if unpaidInvoice}
	<div
		class="dialog-layer"
		role="presentation"
		onclick={(event) =>
			event.currentTarget === event.target && closeUnpaidDialog()}
	>
		<form
			class="settlement-dialog"
			onsubmit={(event) => {
				event.preventDefault();
				markInvoiceUnpaid();
			}}
		>
			<h2>Set Invoice Unpaid</h2>
			<p>
				Explain why {unpaidInvoice.invoice_no} is being changed to unpaid.
			</p>
			{#if unpaidError}<div class="dialog-error" role="alert">
					{unpaidError}
				</div>{/if}
			<label
				>Remark
				<textarea
					rows="4"
					maxlength="1000"
					bind:value={unpaidRemark}
					required
					placeholder="Enter the reason..."></textarea>
			</label>
			<div class="dialog-actions">
				<button
					class="btn"
					type="button"
					onclick={closeUnpaidDialog}
					disabled={unpaidSaving}>Cancel</button
				>
				<button
					class="btn btn-primary"
					type="submit"
					disabled={unpaidSaving || !unpaidRemark.trim()}
					>{unpaidSaving ? "Saving…" : "Confirm Unpaid"}</button
				>
			</div>
		</form>
	</div>
{/if}

<style>
	.settlement-panel {
		overflow: hidden;
	}
	.payment-summary {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 220px));
		gap: 12px;
		margin-bottom: 14px;
	}
	.summary-card {
		display: flex;
		align-items: center;
		gap: 13px;
		padding: 16px 18px;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		background: white;
		box-shadow: 0 2px 8px rgb(15 23 42 / 3%);
	}
	.summary-icon {
		display: grid;
		width: 34px;
		height: 34px;
		place-items: center;
		flex: none;
		border-radius: 50%;
		color: white;
		font-size: 20px;
		font-weight: 800;
	}
	.paid-summary .summary-icon {
		background: #0a8f67;
	}
	.unpaid-summary .summary-icon {
		background: #d92d4c;
	}
	.summary-card div {
		display: grid;
		gap: 2px;
	}
	.summary-card strong {
		color: #172033;
		font-size: 22px;
		line-height: 1;
	}
	.summary-card div span {
		color: #64748b;
		font-size: 12px;
		font-weight: 650;
	}
	.settlement-requirement {
		margin: 6px 12px;
		color: #64748b;
		font-size: 12px;
		line-height: 1.4;
	}
	:global(.settlement-search-scope) {
		width: 140px;
		flex: 0 0 140px;
	}
	.settlement-search-toolbar .search-field {
		flex: 1 1 220px;
		max-width: none;
	}
	.settlement-search-toolbar .search-field .control {
		width: 100%;
	}
	.settlement-date-filter {
		display: grid;
		width: 190px;
		gap: 4px;
		color: #536078;
		font-size: 11px;
		font-weight: 700;
	}
	:global(.filter-select.compact.settlement-search-scope) {
		width: 140px;
		flex: 0 0 140px;
	}
	:global(.filter-select.compact.settlement-status-filter) {
		width: 190px;
		flex: 0 0 190px;
	}
	.settlement-date-range {
		display: flex;
		justify-content: flex-end;
		gap: 12px;
		padding: 10px 16px;
		border-bottom: 1px solid #e5e7eb;
		background: #f8fafc;
	}
	.settlement-date-range :global(.column-picker) {
		align-self: flex-end;
	}
	.state {
		padding: 28px;
		color: #64748b;
		text-align: center;
	}
	.state h3 {
		margin: 0 0 6px;
		color: #111827;
	}
	.state p {
		margin: 0;
	}
	.state.error {
		color: #b42318;
	}
	.status-badge {
		display: inline-flex;
		min-width: 92px;
		align-items: center;
		justify-content: center;
		gap: 6px;
		padding: 5px 10px;
		border-radius: 999px;
		background: #fff1f2;
		color: #be123c;
		font-size: 12px;
		font-weight: 700;
	}
	.status-badge.settled {
		background: #dff8ee;
		color: #087a55;
	}
	.status-icon {
		display: grid;
		width: 17px;
		height: 17px;
		place-items: center;
		border-radius: 50%;
		background: #be123c;
		color: white;
		font-size: 12px;
		line-height: 1;
	}
	.status-badge.settled .status-icon {
		background: #087a55;
	}
	.payment-badge {
		display: inline-flex;
		min-width: 66px;
		align-items: center;
		justify-content: center;
		gap: 8px;
		padding: 5px 10px;
		border-radius: 999px;
		background: #fee2e2;
		color: #c81e3a;
		font-size: 12px;
		font-weight: 700;
	}
	.payment-badge.paid {
		background: #ccfbf1;
		color: #0f766e;
	}
	.status-action {
		min-width: 122px;
		box-sizing: border-box;
		justify-content: space-between;
		border: 1px solid #ccd4e0;
		border-radius: 9px;
		background: #fff;
		padding: 9px 12px;
		font-family: inherit;
		text-align: left;
		cursor: pointer;
		box-shadow: 0 1px 2px rgb(15 23 42 / 3%);
		transition:
			border-color 0.15s ease,
			box-shadow 0.15s ease;
	}
	.payment-badge.status-action {
		color: #c81e3a;
	}
	.payment-badge.status-action.paid {
		background: #fff;
		color: #0f766e;
	}
	.status-badge.status-action {
		min-width: 146px;
		background: #fff;
	}
	.status-action:hover {
		border-color: #aebbd0;
		box-shadow: 0 2px 5px rgb(15 23 42 / 7%);
	}
	.status-action:focus-visible {
		outline: 3px solid rgb(49 94 231 / 28%);
		outline-offset: 2px;
	}
	.status-chevron {
		width: 6px;
		height: 6px;
		flex: none;
		margin: -3px 1px 0 0;
		border-right: 1.5px solid currentColor;
		border-bottom: 1.5px solid currentColor;
		opacity: 0.72;
		transform: rotate(45deg);
		transition: transform 0.15s ease;
	}
	.status-action[aria-expanded="true"] .status-chevron {
		margin-top: 3px;
		transform: rotate(225deg);
	}
	.status-dropdown {
		position: fixed;
		top: var(--menu-top);
		left: var(--menu-left);
		z-index: 2000;
		display: grid;
		min-width: 150px;
		overflow: hidden;
		border: 1px solid #e5e7eb;
		border-radius: 9px;
		background: white;
		box-shadow: 0 10px 30px rgb(15 23 42 / 16%);
	}
	.status-dropdown button {
		width: 100%;
		padding: 10px 13px;
		border: 0;
		background: white;
		color: #202939;
		font: inherit;
		font-size: 13px;
		font-weight: 650;
		text-align: left;
		cursor: pointer;
	}
	.status-dropdown button:hover:not(:disabled) {
		background: #f3f6fb;
	}
	.status-dropdown button.unpaid-option {
		color: #be123c;
	}
	.status-dropdown button:disabled {
		color: #98a2b3;
		cursor: default;
	}
	.remark-cell {
		max-width: 320px;
		color: #536078;
		line-height: 1.45;
		white-space: normal;
	}
	.invoice-link {
		color: #172033;
		font-weight: 700;
		text-decoration: none;
	}
	.invoice-link:hover {
		color: #2554c7;
		text-decoration: underline;
	}
	.settlement-date {
		white-space: nowrap;
	}
	.action-column {
		width: 70px;
		text-align: right !important;
	}
	.action-cell {
		text-align: right;
	}
	.more-button {
		display: inline-flex;
		width: 38px;
		height: 36px;
		align-items: center;
		justify-content: center;
		padding: 0;
		border: 1px solid #d7dce5;
		border-radius: 8px;
		background: white;
		color: #536078;
		font-size: 20px;
		font-weight: 700;
		cursor: pointer;
		transition:
			background 0.15s ease,
			border-color 0.15s ease;
	}
	.more-button:hover {
		border-color: #bfc7d4;
		background: #f5f7fb;
	}
	.action-dropdown {
		position: fixed;
		top: var(--menu-top);
		left: var(--menu-left);
		z-index: 2000;
		display: grid;
		width: 180px;
		max-height: calc(100vh - 16px);
		overflow: hidden auto;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		background: white;
		box-shadow: 0 10px 30px rgb(15 23 42 / 14%);
	}
	.action-dropdown button,
	.action-dropdown a {
		display: block;
		width: 100%;
		box-sizing: border-box;
		padding: 11px 14px;
		border: 0;
		background: white;
		color: #202939;
		font-size: 14px;
		font-weight: 500;
		text-align: left;
		text-decoration: none;
		cursor: pointer;
	}
	.action-dropdown button:hover:not(:disabled),
	.action-dropdown a:hover {
		background: #f3f4f6;
		color: #202939;
	}
	.action-dropdown .reopen-option:not(:disabled) {
		color: #be123c;
	}
	.action-dropdown .reopen-option:hover:not(:disabled) {
		background: #fff1f1;
		color: #be123c;
	}
	.action-dropdown button:disabled {
		background: white;
		color: #9ca3af;
		cursor: not-allowed;
	}
	.menu-divider {
		height: 1px;
		background: #e5e7eb;
	}
	.dialog-layer {
		position: fixed;
		inset: 0;
		z-index: 100;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(15 23 42 / 45%);
	}
	.settlement-dialog {
		width: min(100%, 440px);
		padding: 25px;
		border-radius: 12px;
		background: white;
		box-shadow: 0 24px 70px rgb(15 23 42 / 24%);
	}
	.settlement-dialog h2 {
		margin: 0;
		color: #172033;
		font-size: 21px;
	}
	.settlement-dialog > p {
		margin: 6px 0 22px;
		color: #64748b;
	}
	.settlement-dialog label {
		display: grid;
		gap: 8px;
		color: #344054;
		font-size: 13px;
		font-weight: 700;
	}
	.settlement-dialog label span {
		color: #dc2626;
	}
	.settlement-dialog select,
	.settlement-dialog textarea {
		box-sizing: border-box;
		width: 100%;
		min-height: 42px;
		padding: 10px 12px;
		border: 1px solid #ccd4e0;
		border-radius: 9px;
		outline: none;
		background: white;
		color: #172033;
		font: inherit;
		font-weight: 400;
	}
	.settlement-dialog textarea {
		resize: vertical;
		line-height: 1.5;
	}
	.settlement-dialog select:focus,
	.settlement-dialog textarea:focus {
		border-color: #315ee7;
		box-shadow: 0 0 0 3px rgb(49 94 231 / 12%);
	}
	.dialog-error {
		margin-bottom: 16px;
		padding: 10px 12px;
		border-radius: 8px;
		background: #fef2f2;
		color: #b42318;
		font-size: 12px;
	}
	.dialog-actions {
		display: flex;
		justify-content: flex-end;
		gap: 9px;
		margin-top: 22px;
	}
	@media (max-width: 900px) {
		.payment-summary {
			grid-template-columns: 1fr 1fr;
		}
		.settlement-date-filter {
			width: 190px;
		}
		.data-table {
			min-width: 1120px;
		}
		.action-cell {
			white-space: nowrap;
		}
	}
	@media (max-width: 480px) {
		.payment-summary {
			grid-template-columns: 1fr;
		}
		.settlement-search-toolbar {
			display: grid;
			grid-template-columns: minmax(0, 1fr);
		}
		.settlement-date-filter,
		:global(.settlement-date-range .column-picker),
		:global(.settlement-date-range .column-picker summary),
		:global(.settlement-search-scope),
		:global(.settlement-status-filter) {
			width: 100%;
		}
		:global(.settlement-search-scope) {
			grid-column: 1 / -1;
			width: 100%;
			flex: 1 1 100%;
		}
		:global(.filter-select.compact.settlement-search-scope) {
			width: 100%;
			flex: 1 1 100%;
		}
		.settlement-search-toolbar .search-field {
			grid-column: 1 / -1;
			width: 100%;
		}
		.settlement-search-toolbar > .btn {
			grid-column: 1 / -1;
			width: 100%;
		}
		:global(.settlement-status-filter) {
			grid-column: 1 / -1;
			width: 100%;
			flex: 1 1 100%;
		}
		.settlement-date-range {
			flex-direction: column;
		}
	}
</style>
