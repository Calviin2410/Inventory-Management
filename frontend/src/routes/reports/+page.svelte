<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";

    import { api } from "$lib/api.js";
    import { user } from "$lib/stores/auth.js";

    import Nav from "$lib/Nav.svelte";
    import SkeletonTable from "$lib/SkeletonTable.svelte";
    import DateInput from "$lib/DateInput.svelte";
    import FilterSelect from "$lib/FilterSelect.svelte";
    import Toast from "$lib/Toast.svelte";
    import { formatDate } from "$lib/format.js";

    let invoices = $state([]);
    let filteredInvoices = $state([]);

    let loading = $state(true);
    let exporting = $state(false);
    let errorMessage = $state("");
    let successMessage = $state("");
	let statusMenu = $state({ invoiceId: null, kind: null, top: 0, left: 0 });
    let statusMenuInvoice = $derived(invoices.find((invoice) => invoice.id === statusMenu.invoiceId) ?? null);
    let paymentInvoice = $state(null);
    let paymentMethod = $state("cash");
    let paymentDate = $state("");
    let paymentError = $state("");
    let paymentSaving = $state(false);
    let unpaidInvoice = $state(null);
    let unpaidRemark = $state("");
    let unpaidError = $state("");
    let unpaidSaving = $state(false);
    let settlementInvoice = $state(null);
    let settlementAction = $state("");
    let settlementRemark = $state("");
    let settlementError = $state("");
    let settlementSaving = $state(false);

    let fromDate = $state("");
    let toDate = $state("");
	let searchInput = $state("");
	let appliedSearch = $state("");
	let searchBy = $state("all");
	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalInvoices = $state(0);
	let invoiceSortDirection = $state("");
	let invoiceDateSortDirection = $state("desc");
	let paidInvoices = $state(0);
	let unpaidInvoices = $state(0);
	let settledInvoices = $state(0);
	let unsettledInvoices = $state(0);
	let paymentStatusFilter = $state("");
	let settlementStatusFilter = $state("");
	const reportColumns = [
		{ key: "invoice", label: "Invoice" },
		{ key: "invoiceDate", label: "Invoice Date" },
		{ key: "customer", label: "Customer" },
		{ key: "barrel", label: "Barrel" },
		{ key: "rentalStart", label: "Rental Start" },
		{ key: "rentalEnd", label: "Rental End" },
		{ key: "invoiceAmount", label: "Total Amount" },
		{ key: "createdBy", label: "Created By" },
		{ key: "wasteSellAmount", label: "Sell Waste Amount" },
		{ key: "paymentStatus", label: "Payment Status" },
		{ key: "settlementStatus", label: "Settlement Status" },
	];
	let visibleColumns = $state(Object.fromEntries(reportColumns.map((column) => [column.key, true])));
	let allColumnsVisible = $derived(reportColumns.every((column) => visibleColumns[column.key]));

	function toggleColumn(key) {
		if (visibleColumns[key] && reportColumns.filter((column) => visibleColumns[column.key]).length === 1) return;
		visibleColumns = { ...visibleColumns, [key]: !visibleColumns[key] };
	}

	function toggleAllColumns() {
		visibleColumns = Object.fromEntries(reportColumns.map((column) => [column.key, true]));
	}

    function reportParams(page = 1) {
		const params = { page };
		if (invoiceSortDirection) { params.sort = "invoice_no"; params.direction = invoiceSortDirection; }
		else if (invoiceDateSortDirection) { params.sort = "issued_date"; params.direction = invoiceDateSortDirection; }
		if (fromDate) params.from_date = fromDate;
		if (toDate) params.to_date = toDate;
		if (appliedSearch) params.search = appliedSearch;
		if (searchBy !== "all") params.search_by = searchBy;
		if (paymentStatusFilter) params.payment_status = paymentStatusFilter;
		if (settlementStatusFilter) params.settlement_status = settlementStatusFilter;
		return params;
	}

	function summaryHref({ paymentStatus, settlementStatus } = {}) {
		const params = new URLSearchParams();
		if (paymentStatus) params.set("payment_status", paymentStatus);
		if (settlementStatus) params.set("settlement_status", settlementStatus);
		if (fromDate) params.set("from_date", fromDate);
		if (toDate) params.set("to_date", toDate);
		return `/reports?${params.toString()}`;
	}

	function invoiceSummaryHref(status = "") {
		const params = new URLSearchParams();
		if (status) params.set("status", status);
		if (fromDate) params.set("from_date", fromDate);
		if (toDate) params.set("to_date", toDate);
		const query = params.toString();
		return `/invoices${query ? `?${query}` : ""}`;
	}

	function submitSearch(event) {
		event.preventDefault();
		appliedSearch = searchInput.trim();
		loadReports(1);
	}

	function toggleSort(column) {
		if (column === "invoice_no") {
			invoiceSortDirection = invoiceSortDirection === "asc" ? "desc" : "asc";
			invoiceDateSortDirection = "";
		} else {
			invoiceDateSortDirection = invoiceDateSortDirection === "asc" ? "desc" : "asc";
			invoiceSortDirection = "";
		}
		loadReports(1);
	}

	function settledInvoiceHref(invoice) {
		return `/invoices/${invoice.id}${invoice.settlement_status === "settled" ? "?source=settlement" : ""}`;
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

    function togglePaymentMenu(invoice, kind, event) {
        if (statusMenu.invoiceId === invoice.id && statusMenu.kind === kind) {
            statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
            return;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        const width = 150;
        const height = 100;
        const padding = 10;
        statusMenu = {
            invoiceId: invoice.id,
            kind,
            top: Math.max(padding, Math.min(rect.bottom + 5, window.innerHeight - height - padding)),
            left: Math.max(padding, Math.min(rect.left, window.innerWidth - width - padding)),
        };
    }

    function closeStatusMenuOnOutsideClick(event) {
        const target = event.target;
        if (target instanceof Element && target.closest(".report-status-control, .payment-dropdown")) return;
        statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
    }

    function openPaymentDialog(invoice) {
        statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
        paymentInvoice = invoice;
        paymentMethod = "cash";
        paymentDate = malaysiaToday();
        paymentError = "";
    }

    function openUnpaidDialog(invoice) {
        statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
        unpaidInvoice = invoice;
        unpaidRemark = "";
        unpaidError = "";
    }

    async function markInvoicePaid() {
        if (!paymentInvoice || paymentSaving || !paymentDate) return;
        paymentSaving = true;
        paymentError = "";
        try {
            await api.updateInvoice(paymentInvoice.id, {
                status: "paid",
                payment_method: paymentMethod,
                payment_date: paymentDate,
            });
            successMessage = `${paymentInvoice.invoice_no} marked as paid.`;
            paymentInvoice = null;
            await loadReports(currentPage);
        } catch (error) {
            paymentError = error?.message || "Unable to record payment.";
        } finally {
            paymentSaving = false;
        }
    }

    async function markInvoiceUnpaid() {
        if (!unpaidInvoice || unpaidSaving || !unpaidRemark.trim()) return;
        unpaidSaving = true;
        unpaidError = "";
        try {
            await api.updateInvoice(unpaidInvoice.id, {
                status: "unpaid",
                unpaid_remark: unpaidRemark.trim(),
            });
            successMessage = `${unpaidInvoice.invoice_no} marked as unpaid.`;
            unpaidInvoice = null;
            await loadReports(currentPage);
        } catch (error) {
            unpaidError = error?.errors?.unpaid_remark?.[0] || error?.message || "Unable to set invoice as unpaid.";
        } finally {
            unpaidSaving = false;
        }
    }

    function openSettlementDialog(invoice, action) {
        statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
        settlementInvoice = invoice;
        settlementAction = action;
        settlementRemark = "";
        settlementError = "";
    }

    async function submitSettlementUpdate() {
        if (!settlementInvoice || settlementSaving || !settlementRemark.trim()) return;
        settlementSaving = true;
        settlementError = "";
        try {
            await api.updateSettlement(settlementInvoice.id, {
                action: settlementAction,
                remark: settlementRemark.trim(),
            });
            successMessage = settlementAction === "settle"
                ? settlementInvoice.invoice_no + " settled."
                : settlementInvoice.invoice_no + " settlement reopened.";
            settlementInvoice = null;
            await loadReports(currentPage);
        } catch (error) {
            settlementError = error?.errors?.remark?.[0] || error?.message || "Unable to update settlement status.";
        } finally {
            settlementSaving = false;
        }
    }

    async function loadReports(page = 1) {
        loading = true;
        errorMessage = "";

        try {
            const result = await api.getRentalReport(reportParams(page));

            invoices = result?.data ?? [];

            filteredInvoices = invoices;
			currentPage = result?.current_page ?? 1;
			lastPage = result?.last_page ?? 1;
			totalInvoices = result?.summary?.total_invoices ?? result?.total ?? 0;
			paidInvoices = result?.summary?.paid_invoices ?? 0;
			unpaidInvoices = result?.summary?.unpaid_invoices ?? 0;
			settledInvoices = result?.summary?.settled_invoices ?? 0;
			unsettledInvoices = result?.summary?.unsettled_invoices ?? 0;
        } catch (error) {
            errorMessage =
                error instanceof Error
                    ? error.message
                    : "Unable to load report";
        } finally {
            loading = false;
        }
    }

    async function generateReport() {
        errorMessage = "";

        if (fromDate && toDate && fromDate > toDate) {
            errorMessage = "From Invoice Date cannot be later than To Invoice Date.";

            return;
        }

        await loadReports(1);
    }

    async function clearFilter() {
        fromDate = "";
        toDate = "";
		searchInput = "";
		appliedSearch = "";
		searchBy = "all";
		paymentStatusFilter = "";
		settlementStatusFilter = "";

        await loadReports(1);
    }

    function excelDate(value) {
        if (!value) {
            return null;
        }

        const [year, month, day] = value.slice(0, 10).split("-").map(Number);

        if (!year || !month || !day) {
            return null;
        }

        return new Date(year, month - 1, day);
    }

    function exportRows(sourceInvoices) {
        return sourceInvoices.flatMap((invoice) => {
            const items = invoice.items?.length ? invoice.items : [null];

            return items.map((item, itemIndex) => ({
                invoice: invoice.invoice_no ?? "",
				invoiceDate: excelDate(invoice.issued_date),
                customer: invoice.customer?.name ?? "",
                barrel: item?.barrel?.code ?? "",
                rentalStart: excelDate(item?.rental_start),
                rentalEnd: excelDate(item?.rental_end),
				invoiceAmount: itemIndex === 0 ? Number(invoice.total_amount ?? 0) : null,
				wasteSellAmount: itemIndex === 0 ? Number(invoice.waste_sell_amount ?? 0) : null,
				settlementStatus: invoice.settlement_status === "settled" ? "Settled" : "Unsettled",
				paymentStatus: invoice.status === "paid" ? "Paid" : "Unpaid",
            }));
        });
    }

    async function exportExcel() {
        if (exporting || loading || filteredInvoices.length === 0) {
            return;
        }

        exporting = true;
        errorMessage = "";

        try {
			const exportInvoices = await api.getRentalReport({
				...reportParams(),
				export: 1,
			});
            const ExcelJS = (await import("exceljs")).default;
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("Rental Report", {
                views: [{ state: "frozen", ySplit: 6 }],
            });

            workbook.creator = "Inventory Management System";
            workbook.created = new Date();

            worksheet.columns = [
                { key: "invoice", width: 18 },
				{ key: "invoiceDate", width: 18 },
                { key: "customer", width: 26 },
                { key: "barrel", width: 16 },
                { key: "rentalStart", width: 18 },
                { key: "rentalEnd", width: 18 },
				{ key: "invoiceAmount", width: 20 },
				{ key: "wasteSellAmount", width: 22 },
				{ key: "settlementStatus", width: 20 },
				{ key: "paymentStatus", width: 16 },
            ];

			worksheet.mergeCells("A1:J1");
            worksheet.getCell("A1").value = "Rental Report";
            worksheet.getCell("A1").font = {
                bold: true,
                color: { argb: "FFFFFFFF" },
                size: 18,
            };
            worksheet.getCell("A1").fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FF2563EB" },
            };
            worksheet.getCell("A1").alignment = { vertical: "middle" };
            worksheet.getRow(1).height = 32;

			worksheet.mergeCells("A2:J2");
            worksheet.getCell("A2").value = `Invoice Date Period: ${fromDate || "All dates"} to ${toDate || "All dates"}`;
            worksheet.getCell("A2").font = { color: { argb: "FF475569" } };

			worksheet.mergeCells("A3:J3");
			const exportPaid = exportInvoices.filter((invoice) => invoice.status === "paid").length;
			const exportUnpaid = exportInvoices.filter((invoice) => invoice.status === "unpaid").length;
			const exportSettled = exportInvoices.filter((invoice) => invoice.settlement_status === "settled").length;
			const exportUnsettled = exportInvoices.filter((invoice) => invoice.settlement_status !== "settled").length;
            worksheet.getCell("A3").value =
				`Total: ${exportInvoices.length}   |   Paid: ${exportPaid}   |   Unpaid: ${exportUnpaid}   |   Settled: ${exportSettled}   |   Unsettled: ${exportUnsettled}`;
            worksheet.getCell("A3").font = { bold: true };

            const headerRow = worksheet.getRow(5);
            headerRow.values = [
                "Invoice",
				"Invoice Date",
                "Customer",
                "Barrel",
                "Rental Start",
                "Rental End",
				"Invoice Amount",
				"Sell Waste Amount",
				"Settlement Status",
				"Payment Status",
            ];
            headerRow.height = 24;
            headerRow.eachCell((cell) => {
                cell.font = { bold: true, color: { argb: "FFFFFFFF" } };
                cell.fill = {
                    type: "pattern",
                    pattern: "solid",
                    fgColor: { argb: "FF1E3A8A" },
                };
                cell.alignment = { vertical: "middle" };
            });

            for (const rowData of exportRows(exportInvoices)) {
                const row = worksheet.addRow(rowData);
				row.getCell("invoiceDate").numFmt = "dd mmm yyyy";
                row.getCell("rentalStart").numFmt = "dd mmm yyyy";
                row.getCell("rentalEnd").numFmt = "dd mmm yyyy";
				row.getCell("invoiceAmount").numFmt = '"RM "#,##0.00';
				row.getCell("wasteSellAmount").numFmt = '"RM "#,##0.00';

				const statusCell = row.getCell("paymentStatus");
                statusCell.font = {
                    bold: true,
                    color: {
                        argb:
							rowData.paymentStatus === "Paid"
                                ? "FF047857"
                                : "FFDC2626",
                    },
                };
            }

			worksheet.autoFilter = "A5:J5";

            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], {
                type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            const period =
                fromDate || toDate
                    ? `${fromDate || "start"}-to-${toDate || "latest"}`
                    : "all-dates";

            link.href = url;
            link.download = `rental-report-${period}.xlsx`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch (error) {
            console.error("Unable to export report:", error);
            errorMessage = "Unable to export the report. Please try again.";
        } finally {
            exporting = false;
        }
    }

	    onMount(async () => {
        try {
            const params = new URLSearchParams(window.location.search);
            fromDate = params.get("from_date") ?? "";
            toDate = params.get("to_date") ?? "";
            const paymentStatus = params.get("payment_status");
            const settlementStatus = params.get("settlement_status");
            if (["paid", "unpaid"].includes(paymentStatus)) paymentStatusFilter = paymentStatus;
            if (["settled", "unsettled"].includes(settlementStatus)) settlementStatusFilter = settlementStatus;

            const currentUser = await api.me();

            user.set(currentUser);

			if (!["admin", "staff", "normal_staff"].includes(currentUser?.role)) {
                goto("/dashboard");
                return;
            }

            await loadReports(1);
        } catch (error) {
            console.error("Unable to verify user:", error);

            user.set(null);

            goto("/login");
        }
    });
</script>

<Nav />
<svelte:window onclick={closeStatusMenuOnOutsideClick} />

<main class="app-page">
    <header class="page-heading">
        <div>
            <p class="eyebrow">REPORTS</p>

            <h1>Rental Report</h1>

            <p class="subtitle">
                Review rental activity by invoice date.
            </p>
        </div>
    </header>

    <!-- Search and report actions -->
    <section class="filter-card">
        <div class="filter-grid">
            <form id="report-search-form" class="report-search" onsubmit={submitSearch}>
				<div class="search-control">
					<FilterSelect compact className="search-scope" ariaLabel="Search field" bind:value={searchBy} options={[{ value: "all", label: "All fields" }, { value: "invoice", label: "Invoice" }, { value: "customer", label: "Customer" }, { value: "barrel", label: "Barrel" }]} />
					<input
						class="control"
						type="search"
						placeholder={searchBy === "barrel" ? "Barrel code..." : searchBy === "invoice" ? "Invoice number..." : searchBy === "customer" ? "Customer name..." : "Invoice, customer or barrel..."}
						bind:value={searchInput}
					/>
				</div>
			</form>
            <div class="filter-actions">
				<button class="btn btn-primary" type="submit" form="report-search-form" disabled={loading}>Search</button>
                {#if $user?.role === "admin"}
                    <button
                        type="button"
                        class="btn btn-export"
                        onclick={exportExcel}
                        disabled={loading || filteredInvoices.length === 0 || exporting}
                        aria-busy={exporting}
                    >
                        <span aria-hidden="true">⇩</span>
                        {exporting ? "Exporting..." : "Export Excel"}
                    </button>
                {/if}

                <button type="button" class="btn" onclick={clearFilter}>
                    Clear
                </button>
            </div>
        </div>
    </section>

    {#if errorMessage}
        <div class="error-message">
            {errorMessage}
        </div>
    {/if}

    {#if loading}
        <SkeletonTable rows={6} columns={6} />
    {:else}
        <!-- Summary -->
        <section class="summary-section">
            <h2>Summary</h2>

            <div class="summary-grid">
                <a class="summary-card" href={invoiceSummaryHref()}>
                    <span> Total Invoices </span>

                    <strong>
                        {totalInvoices}
                    </strong>
                </a>

                <a class="summary-card" href={invoiceSummaryHref("paid")}>
                    <span> Paid </span>

                    <strong>
                        {paidInvoices}
                    </strong>
                </a>

                <a class="summary-card" href={invoiceSummaryHref("unpaid")}>
                    <span> Unpaid </span>

                    <strong>
                        {unpaidInvoices}
                    </strong>
                </a>

                <a class="summary-card" href={summaryHref({ settlementStatus: "settled" })}>
                    <span> Settled Invoices </span>

                    <strong>
						{settledInvoices}
					</strong>
				</a>

				<a class="summary-card" href={summaryHref({ settlementStatus: "unsettled" })}>
					<span> Unsettled Invoices </span>

					<strong>
						{unsettledInvoices}
                    </strong>
                </a>
            </div>
        </section>

        <!-- Rental Records -->
        <section class="report-section">
            <div class="section-heading">
                <div>
                    <h2>Rental Records</h2>

                    <p>
                        Invoice and rental item activity for the selected
                        period.
                    </p>
                </div>

				<div class="report-controls">
					<div class="report-date-range">
						<div class="field">
							<label for="fromDate">From Invoice Date</label>
							<DateInput id="fromDate" bind:value={fromDate} max={toDate} onchange={generateReport} ariaLabel="Select report from date" />
						</div>
						<div class="field">
							<label for="toDate">To Invoice Date</label>
							<DateInput id="toDate" bind:value={toDate} min={fromDate} onchange={generateReport} ariaLabel="Select report to date" />
						</div>
					</div>

					<details class="column-picker">
						<summary>Columns <span class="select-chevron" aria-hidden="true"></span></summary>
						<div class="column-menu">
							<label class="all-columns">
								<input type="checkbox" checked={allColumnsVisible} onchange={toggleAllColumns} />
								<span>All</span>
							</label>
							{#each reportColumns as column}
								<label>
									<input type="checkbox" checked={visibleColumns[column.key]} onchange={() => toggleColumn(column.key)} />
									<span>{column.label}</span>
								</label>
							{/each}
						</div>
					</details>
				</div>
            </div>

            {#if filteredInvoices.length === 0}
                <div class="state">No records found.</div>
            {:else}
                <div class="table-card">
                    <table class:wide-table={Object.values(visibleColumns).filter(Boolean).length >= 9}>
                        <thead>
                            <tr>
                                {#if visibleColumns.invoice}<th aria-sort={invoiceSortDirection === "asc" ? "ascending" : invoiceSortDirection === "desc" ? "descending" : "none"}><button class="sort-header" type="button" onclick={() => toggleSort("invoice_no")}>Invoice <span aria-hidden="true">{invoiceSortDirection === "asc" ? "↑" : invoiceSortDirection === "desc" ? "↓" : "↕"}</span></button></th>{/if}

								{#if visibleColumns.invoiceDate}<th aria-sort={invoiceDateSortDirection === "asc" ? "ascending" : invoiceDateSortDirection === "desc" ? "descending" : "none"}><button class="sort-header" type="button" onclick={() => toggleSort("issued_date")}>Invoice Date <span aria-hidden="true">{invoiceDateSortDirection === "asc" ? "↑" : invoiceDateSortDirection === "desc" ? "↓" : "↕"}</span></button></th>{/if}

								{#if visibleColumns.customer}<th> Customer </th>{/if}

								{#if visibleColumns.barrel}<th class="barrel-column"> Barrel </th>{/if}

								{#if visibleColumns.rentalStart}<th class="rental-start-column"> Rental Start </th>{/if}

								{#if visibleColumns.rentalEnd}<th> Rental End </th>{/if}

								{#if visibleColumns.invoiceAmount}<th>Total Amount</th>{/if}
								{#if visibleColumns.createdBy}<th>Created By</th>{/if}
								{#if visibleColumns.wasteSellAmount}<th>Sell Waste Amount</th>{/if}
								{#if visibleColumns.paymentStatus}<th class="payment-status-column"> Payment Status </th>{/if}
								{#if visibleColumns.settlementStatus}<th class="settlement-status-column"> Settlement Status </th>{/if}
                            </tr>
                        </thead>

                        <tbody>
                            {#each filteredInvoices as invoice (invoice.id)}
                                {#if invoice.items?.length}
                                    {#each invoice.items as item, itemIndex}
                                        <tr>
											{#if visibleColumns.invoice}
							<td><a class="invoice-link" href={settledInvoiceHref(invoice)}>{invoice.invoice_no}</a></td>
											{/if}

							{#if visibleColumns.invoiceDate}<td>{formatDate(invoice.issued_date)}</td>{/if}

							{#if visibleColumns.customer}<td>
                                                {invoice.customer?.name ?? "-"}
                                            </td>{/if}

										{#if visibleColumns.barrel}<td class="barrel-column">
                                                {item.barrel?.code ?? "-"}
                                            </td>{/if}

											{#if visibleColumns.rentalStart}<td class="rental-start-column">
                                                {formatDate(item.rental_start)}
                                            </td>{/if}

							{#if visibleColumns.rentalEnd}<td>
                                                {formatDate(item.rental_end)}
                                            </td>{/if}
							{#if visibleColumns.invoiceAmount}<td>{itemIndex === 0 ? new Intl.NumberFormat("en-MY", { style: "currency", currency: "MYR" }).format(Number(invoice.total_amount ?? 0)) : "—"}</td>{/if}
							{#if visibleColumns.createdBy}<td>{invoice.created_by?.name ?? "—"}</td>{/if}
							{#if visibleColumns.wasteSellAmount}<td>{itemIndex === 0 ? new Intl.NumberFormat("en-MY", { style: "currency", currency: "MYR" }).format(Number(invoice.waste_sell_amount ?? 0)) : "—"}</td>{/if}

							{#if visibleColumns.paymentStatus}<td class="payment-status-column">
								{#if invoice.status !== "cancelled"}
									{#if invoice.settlement_status !== "settled"}
									<button class="payment-status-control status-action report-status-control" class:paid={invoice.status === "paid"} class:unpaid={invoice.status === "unpaid"} type="button" aria-expanded={statusMenu.invoiceId === invoice.id && statusMenu.kind === "payment"} onclick={(event) => togglePaymentMenu(invoice, "payment", event)}>
										{invoice.status === "paid" ? "Paid" : "Unpaid"}<span class="status-chevron" aria-hidden="true"></span>
									</button>
									{:else}<span class="payment-status-control" class:paid={invoice.status === "paid"}>{invoice.status === "paid" ? "Paid" : "Unpaid"}</span>{/if}
								{:else}<span class="status-badge">Cancelled</span>{/if}
							</td>{/if}

							{#if visibleColumns.settlementStatus}<td class="settlement-status-column">
								{#if $user?.role === "admin"}
											<button class="settlement-badge status-action report-status-control" class:settled={invoice.settlement_status === "settled"} type="button" aria-expanded={statusMenu.invoiceId === invoice.id && statusMenu.kind === "settlement"} onclick={(event) => togglePaymentMenu(invoice, "settlement", event)}>
														<span class="status-icon" aria-hidden="true">{invoice.settlement_status === "settled" ? "✓" : "×"}</span>
														<span class="status-label">{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}</span>
														<span class="status-chevron" aria-hidden="true"></span>
													</button>
								{:else}
									<span class="settlement-badge" class:settled={invoice.settlement_status === "settled"}>{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}</span>
								{/if}
											</td>{/if}

                                        </tr>
                                    {/each}
                                {:else}
                                    <tr>
										{#if visibleColumns.invoice}
							<td><a class="invoice-link" href={settledInvoiceHref(invoice)}>{invoice.invoice_no}</a></td>
										{/if}

					{#if visibleColumns.invoiceDate}<td>{formatDate(invoice.issued_date)}</td>{/if}

					{#if visibleColumns.customer}<td>
                                            {invoice.customer?.name ?? "-"}
                                        </td>{/if}

										{#if visibleColumns.barrel}<td class="barrel-column"> - </td>{/if}

									{#if visibleColumns.rentalStart}<td class="rental-start-column"> - </td>{/if}

					{#if visibleColumns.rentalEnd}<td> - </td>{/if}
					{#if visibleColumns.invoiceAmount}<td>{new Intl.NumberFormat("en-MY", { style: "currency", currency: "MYR" }).format(Number(invoice.total_amount ?? 0))}</td>{/if}
					{#if visibleColumns.createdBy}<td>{invoice.created_by?.name ?? "—"}</td>{/if}
					{#if visibleColumns.wasteSellAmount}<td>{new Intl.NumberFormat("en-MY", { style: "currency", currency: "MYR" }).format(Number(invoice.waste_sell_amount ?? 0))}</td>{/if}

								{#if visibleColumns.paymentStatus}<td class="payment-status-column">
									{#if invoice.status !== "cancelled"}
										{#if invoice.settlement_status !== "settled"}
										<button class="payment-status-control status-action report-status-control" class:paid={invoice.status === "paid"} class:unpaid={invoice.status === "unpaid"} type="button" aria-expanded={statusMenu.invoiceId === invoice.id && statusMenu.kind === "payment"} onclick={(event) => togglePaymentMenu(invoice, "payment", event)}>
											{invoice.status === "paid" ? "Paid" : "Unpaid"}<span class="status-chevron" aria-hidden="true"></span>
										</button>
										{:else}<span class="payment-status-control" class:paid={invoice.status === "paid"}>{invoice.status === "paid" ? "Paid" : "Unpaid"}</span>{/if}
									{:else}<span class="status-badge">Cancelled</span>{/if}
								</td>{/if}

								{#if visibleColumns.settlementStatus}<td class="settlement-status-column">
						{#if $user?.role === "admin"}
										<button class="settlement-badge status-action report-status-control" class:settled={invoice.settlement_status === "settled"} type="button" aria-expanded={statusMenu.invoiceId === invoice.id && statusMenu.kind === "settlement"} onclick={(event) => togglePaymentMenu(invoice, "settlement", event)}>
											<span class="status-icon" aria-hidden="true">{invoice.settlement_status === "settled" ? "✓" : "×"}</span>
											<span class="status-label">{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}</span>
											<span class="status-chevron" aria-hidden="true"></span>
										</button>
						{:else}
							<span class="settlement-badge" class:settled={invoice.settlement_status === "settled"}>{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}</span>
						{/if}
									</td>{/if}

                                    </tr>
                                {/if}
                            {/each}
                        </tbody>
                    </table>
                </div>

				<div class="pagination">
					<div class="pagination-info">
						Page {currentPage} of {lastPage} · {totalInvoices} invoices
					</div>
					<div class="pagination-actions">
						<button type="button" disabled={currentPage <= 1 || loading} onclick={() => loadReports(currentPage - 1)}>Previous</button>
						<button type="button" disabled={currentPage >= lastPage || loading} onclick={() => loadReports(currentPage + 1)}>Next</button>
					</div>
				</div>
            {/if}
        </section>
    {/if}
</main>

{#if statusMenuInvoice}
	<div class="payment-dropdown" style:--menu-top={`${statusMenu.top}px`} style:--menu-left={`${statusMenu.left}px`}>
		{#if statusMenu.kind === "payment"}
			<button type="button" disabled={statusMenuInvoice.status === "paid"} onclick={() => openPaymentDialog(statusMenuInvoice)}>Paid</button>
			<button type="button" class="unpaid-option" disabled={statusMenuInvoice.status !== "paid"} onclick={() => openUnpaidDialog(statusMenuInvoice)}>Unpaid</button>
		{:else if $user?.role === "admin"}
			{#if statusMenuInvoice.settlement_status !== "settled" && !canSettleInvoice(statusMenuInvoice)}
				<p class="settlement-requirement">{settleRequirement(statusMenuInvoice)}</p>
			{/if}
			<button type="button" disabled={!canSettleInvoice(statusMenuInvoice)} onclick={() => openSettlementDialog(statusMenuInvoice, "settle")}>Settle</button>
			<button type="button" class="unpaid-option" disabled={statusMenuInvoice.settlement_status !== "settled"} onclick={() => openSettlementDialog(statusMenuInvoice, "reopen")}>Reopen</button>
		{/if}
	</div>
{/if}

{#if paymentInvoice}
	<div class="report-dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && !paymentSaving && (paymentInvoice = null)}>
		<form class="report-dialog" onsubmit={(event) => { event.preventDefault(); markInvoicePaid(); }}>
			<h2>Record Payment</h2>
			<p>Mark {paymentInvoice.invoice_no} as paid.</p>
			{#if paymentError}<div class="dialog-error" role="alert">{paymentError}</div>{/if}
			<label>Payment Method
				<select bind:value={paymentMethod} required>
					<option value="cash">Cash</option>
					<option value="bank_in">Bank In</option>
				</select>
			</label>
			<label>Payment Date
				<DateInput bind:value={paymentDate} required ariaLabel="Select payment date" />
			</label>
			<div class="dialog-actions">
				<button type="button" class="btn" onclick={() => !paymentSaving && (paymentInvoice = null)}>Cancel</button>
				<button type="submit" class="btn btn-primary" disabled={paymentSaving || !paymentDate}>{paymentSaving ? "Saving…" : "Confirm Paid"}</button>
			</div>
		</form>
	</div>
{/if}

{#if unpaidInvoice}
	<div class="report-dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && !unpaidSaving && (unpaidInvoice = null)}>
		<form class="report-dialog" onsubmit={(event) => { event.preventDefault(); markInvoiceUnpaid(); }}>
			<h2>Set Invoice Unpaid</h2>
			<p>Explain why {unpaidInvoice.invoice_no} is being changed to unpaid.</p>
			{#if unpaidError}<div class="dialog-error" role="alert">{unpaidError}</div>{/if}
			<label>Remark
				<textarea rows="4" maxlength="1000" bind:value={unpaidRemark} required placeholder="Enter the reason..."></textarea>
			</label>
			<div class="dialog-actions">
				<button type="button" class="btn" onclick={() => !unpaidSaving && (unpaidInvoice = null)}>Cancel</button>
				<button type="submit" class="btn btn-primary" disabled={unpaidSaving || !unpaidRemark.trim()}>{unpaidSaving ? "Saving…" : "Confirm Unpaid"}</button>
			</div>
		</form>
	</div>
{/if}
{#if settlementInvoice}
	<div class="report-dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && !settlementSaving && (settlementInvoice = null)}>
		<form class="report-dialog" onsubmit={(event) => { event.preventDefault(); submitSettlementUpdate(); }}>
			<h2>{settlementAction === "settle" ? "Settle Invoice" : "Reopen"}</h2>
			<p>{settlementInvoice.invoice_no}</p>
			{#if settlementError}<div class="dialog-error" role="alert">{settlementError}</div>{/if}
			<label>Remark
				<textarea rows="4" maxlength="1000" bind:value={settlementRemark} required placeholder="Enter the reason or settlement note"></textarea>
			</label>
			<div class="dialog-actions">
				<button type="button" class="btn" onclick={() => !settlementSaving && (settlementInvoice = null)}>Cancel</button>
				<button type="submit" class="btn btn-primary" disabled={settlementSaving || !settlementRemark.trim()}>{settlementSaving ? "Saving…" : settlementAction === "settle" ? "Settle" : "Reopen"}</button>
			</div>
		</form>
	</div>
{/if}
{#if successMessage}<Toast message={successMessage} onclose={() => (successMessage = "")} />{/if}

<style>
    /* =========================
	   REPORT FILTER
	========================= */

    .filter-card {
        margin-bottom: 28px;

        padding: 20px;

        border: 1px solid #e5e7eb;
        border-radius: 10px;

        background: white;
    }
	.filter-grid {
		display: grid;
		grid-template-columns: minmax(280px, 1fr) minmax(0, auto);
		align-items: end;
		gap: 16px;
	}
	.search-control { display: flex; min-width: 0; gap: 10px; }
	.report-search { width: 100%; min-width: 0; }
	.search-control input { width: 100%; min-width: 0; }
	.search-control :global(.search-scope) { flex: 0 0 145px; }

    .field {
        display: flex;
        flex-direction: column;
		min-width: 0;

        gap: 7px;
    }

    .field label {
        color: #374151;

        font-size: 13px;
        font-weight: 600;
    }

    .filter-actions {
        display: flex;
		justify-content: flex-end;
		min-width: 0;
		flex-wrap: nowrap;
        gap: 10px;
    }

	.filter-actions .btn {
		flex: 0 0 auto;
		min-width: 120px;
		white-space: nowrap;
	}

	@media (max-width: 1280px) {
		.filter-grid { grid-template-columns: minmax(0, 1fr); }
		.filter-actions { grid-column: 1 / -1; }
	}

    .btn-export {
        border-color: #15803d;
        background: #f0fdf4;
        color: #166534;
    }

    .btn-export:hover:not(:disabled) {
        border-color: #166534;
        background: #dcfce7;
    }

    .btn-export span {
        margin-right: 5px;
        font-size: 16px;
        line-height: 1;
    }

    /* =========================
	   SUMMARY
	========================= */

    .summary-section {
        margin-bottom: 28px;
    }

    .summary-section h2,
    .report-section h2 {
        margin: 0 0 15px;

        font-size: 20px;
    }

    .summary-grid {
        display: grid;

        grid-template-columns: repeat(5, minmax(0, 1fr));

        gap: 16px;
    }

    .summary-card {
        display: block;
        padding: 20px;

        border: 1px solid #e5e7eb;
        border-radius: 10px;

        background: white;
        color: inherit;
        text-decoration: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }

    .summary-card:hover {
        border-color: #bdc9da;
        box-shadow: 0 5px 14px rgb(15 23 42 / 8%);
        transform: translateY(-1px);
    }

    .summary-card:focus-visible {
        outline: 3px solid rgb(49 94 231 / 30%);
        outline-offset: 2px;
    }

	.settlement-requirement {
		margin: 6px 12px;
		color: #64748b;
		font-size: 12px;
		line-height: 1.4;
	}

    .summary-card span {
        display: block;

        margin-bottom: 10px;

        color: #64748b;

        font-size: 13px;
    }

    .summary-card strong {
        font-size: 28px;
    }

    /* =========================
	   REPORT SECTION
	========================= */

    .report-section {
        min-width: 0;
        margin-top: 30px;
    }

    .section-heading {
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
		gap: 16px;
        margin-bottom: 14px;
    }

    .section-heading h2 {
        margin-bottom: 5px;
    }

    .section-heading p {
        margin: 0;

        color: #64748b;

        font-size: 13px;
    }

	.report-controls { display: flex; align-items: flex-end; gap: 12px; flex: none; }
	.report-date-range { display: grid; grid-template-columns: repeat(2, minmax(170px, 1fr)); gap: 10px; }

	.column-picker { position: relative; flex: none; }
	.column-picker summary { display: flex; min-width: 120px; min-height: 42px; align-items: center; justify-content: space-between; gap: 18px; padding: 0 13px; border: 1px solid #ccd4e0; border-radius: 8px; background: white; color: #172033; font-size: 13px; font-weight: 700; cursor: pointer; list-style: none; }
	.column-picker summary::-webkit-details-marker { display: none; }
	.column-picker[open] summary { border-color: #4771e8; box-shadow: 0 0 0 3px rgb(53 99 233 / 12%); }
	.select-chevron { width: 7px; height: 7px; flex: none; margin: -3px 2px 0 0; border-right: 1.5px solid currentColor; border-bottom: 1.5px solid currentColor; transform: rotate(45deg); transition: transform 0.15s ease; }
	.column-picker[open] .select-chevron { margin-top: 3px; transform: rotate(225deg); }
	.column-menu { position: absolute; top: calc(100% + 6px); right: 0; z-index: 20; display: grid; width: 230px; max-height: 340px; overflow-y: auto; padding: 7px; border: 1px solid #d7deea; border-radius: 9px; background: white; box-shadow: 0 14px 34px rgb(15 23 42 / 16%); }
	.column-menu label { display: flex; align-items: center; gap: 9px; padding: 9px 10px; border-radius: 6px; color: #273348; font-size: 13px; cursor: pointer; }
	.column-menu label:hover { background: #f4f7fb; }
	.column-menu input { width: 16px; height: 16px; margin: 0; accent-color: #315ee7; }
	.column-menu .all-columns { margin-bottom: 4px; border-bottom: 1px solid #e7ebf1; border-radius: 6px 6px 0 0; font-weight: 700; }

    /* =========================
	   REPORT TABLE
	========================= */

    .table-card {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        overscroll-behavior-inline: contain;
        -webkit-overflow-scrolling: touch;

        border: 1px solid #e5e7eb;
        border-radius: 9px;

        background: white;
    }

    table {
        width: 100%;

        border-collapse: collapse;
    }

	table.wide-table {
		min-width: 0;
		table-layout: fixed;
	}

	table.wide-table th,
	table.wide-table td {
		padding-right: 8px;
		padding-left: 8px;
		overflow-wrap: anywhere;
	}

	table.wide-table th {
		font-size: 12px;
		line-height: 1.25;
	}

	table.wide-table td {
		font-size: 13px;
	}

    th {
        padding: 14px 12px;

        border-bottom: 1px solid #e5e7eb;

        background: #f8fafc;

        color: #64748b;

        text-align: left;

        font-size: 13px;
        font-weight: 600;
		line-height: 1.35;
    }

    td {
        padding: 15px 12px;

        border-bottom: 1px solid #e5e7eb;

        font-size: 14px;
		vertical-align: middle;
		line-height: 1.35;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    tbody tr:hover {
        background: #fafafa;
    }

	.invoice-link { color: #172033; font-weight: 700; text-decoration: none; }
	.invoice-link:hover { color: #2554c7; text-decoration: underline; }

    /* =========================
	   STATUS
	========================= */

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-width: 64px;

        padding: 5px 10px;

        border-radius: 6px;

        font-size: 13px;
        font-weight: 600;
    }

	.payment-status-control {
		display: inline-flex;
		min-width: 66px;
		align-items: center;
		justify-content: center;
		gap: 8px;
		padding: 5px 10px;
		border: 0;
		border-radius: 999px;
		background: #fee2e2;
		color: #c81e3a;
		font: inherit;
		font-size: 12px;
		font-weight: 700;
	}
	.payment-status-control.status-action,
	.settlement-badge.status-action {
		min-width: 122px;
		box-sizing: border-box;
		justify-content: space-between;
		padding: 9px 12px;
		border: 1px solid #ccd4e0;
		border-radius: 9px;
		background: white;
		font: inherit;
		font-size: 12px;
		font-weight: 700;
		line-height: 1.2;
		text-align: left;
		cursor: pointer;
		box-shadow: 0 1px 2px rgb(15 23 42 / 3%);
		transition: border-color .15s ease, box-shadow .15s ease;
	}
	.status-action:hover { border-color: #aebbd0; box-shadow: 0 2px 5px rgb(15 23 42 / 7%); }
	.status-action:focus-visible { outline: 3px solid rgb(49 94 231 / 28%); outline-offset: 2px; }
	.payment-status-control.status-action { min-width: 122px; color: #c81e3a; }
	.payment-status-control.status-action.paid { background: white; color: #0f766e; }
	.settlement-badge.status-action { min-width: 146px; background: white; }
	.settlement-badge.status-action.settled { color: #087a55; }
	.payment-status-column { width: 125px; }
	.settlement-status-column { width: 155px; }
	.barrel-column {
		width: 68px;
	}
	.rental-start-column {
		width: 105px;
	}
	table.wide-table .barrel-column {
		padding-right: 4px;
	}
	table.wide-table .rental-start-column {
		padding-left: 4px;
	}
	.settlement-badge.status-action.report-status-control {
		justify-content: flex-start;
		gap: 6px;
		padding-right: 8px;
		padding-left: 8px;
	}
	.settlement-badge.status-action.report-status-control .status-label {
		min-width: 0;
		white-space: nowrap;
	}
	.settlement-badge.status-action.report-status-control .status-chevron {
		margin-left: auto;
	}
	.status-icon { display: grid; width: 17px; height: 17px; flex: none; place-items: center; border-radius: 50%; background: #be123c; color: white; font-size: 12px; line-height: 1; }
	.status-action.settled .status-icon { background: #087a55; }
	.status-chevron { width: 6px; height: 6px; flex: none; margin: -3px 1px 0 0; border-right: 1.5px solid currentColor; border-bottom: 1.5px solid currentColor; opacity: .72; transform: rotate(45deg); transition: transform .15s ease; }
	.status-action[aria-expanded="true"] .status-chevron { margin-top: 3px; transform: rotate(225deg); }
	.payment-dropdown {
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
	.payment-dropdown button {
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
	.payment-dropdown button:hover:not(:disabled) { background: #f3f6fb; }
	.payment-dropdown button.unpaid-option { color: #be123c; }
	.payment-dropdown button:disabled { color: #98a2b3; cursor: default; }
	.report-dialog-layer { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 20px; background: rgb(15 23 42 / 45%); }
	.report-dialog { width: min(100%, 440px); display: grid; gap: 16px; padding: 25px; border-radius: 12px; background: white; box-shadow: 0 24px 70px rgb(15 23 42 / 24%); }
	.report-dialog h2 { margin: 0; color: #172033; font-size: 21px; }
	.report-dialog > p { margin: -10px 0 2px; color: #64748b; }
	.report-dialog label { display: grid; gap: 8px; color: #344054; font-size: 13px; font-weight: 700; }
	.report-dialog select, .report-dialog textarea { box-sizing: border-box; width: 100%; min-height: 42px; padding: 10px 12px; border: 1px solid #ccd4e0; border-radius: 9px; outline: none; background: white; color: #172033; font: inherit; font-weight: 400; }
	.report-dialog textarea { resize: vertical; line-height: 1.5; }
	.report-dialog select:focus, .report-dialog textarea:focus { border-color: #315ee7; box-shadow: 0 0 0 3px rgb(49 94 231 / 12%); }
	.dialog-error { padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; font-size: 12px; }
	.dialog-actions { display: flex; justify-content: flex-end; gap: 9px; }

	.settlement-badge { display: inline-flex; min-width: 82px; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 6px; background: #fff1f2; color: #be123c; font-size: 12px; font-weight: 700; }
	.settlement-badge.settled { background: #dff8ee; color: #087a55; }
	.wide-table .payment-status-control.report-status-control,
	.wide-table .settlement-badge.report-status-control {
		width: 100%;
		min-width: 0;
	}

    /* =========================
	   STATES
	========================= */

    .state {
        padding: 22px;

        border: 1px solid #e5e7eb;
        border-radius: 8px;

        background: white;

        color: #64748b;

        font-size: 14px;
    }

    .error-message {
        margin-bottom: 20px;

        padding: 12px 14px;

        border-radius: 8px;

        background: #fef2f2;
        color: #b42318;

        font-size: 14px;
    }

    /* =========================
	   RESPONSIVE
	========================= */

    @media (max-width: 900px) {
        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
		.report-section,
		.section-heading,
		.report-controls,
		.report-date-range {
			width: 100%;
			min-width: 0;
			max-width: 100%;
		}
		.section-heading { align-items: stretch; flex-direction: column; }
		.report-controls { flex: 1 1 auto; align-items: stretch; flex-direction: column; }
		.report-date-range { grid-template-columns: minmax(0, 1fr); }
		.report-date-range .field,
		.report-date-range :global(.date-input) { min-width: 0; }
		.column-picker summary { width: 100%; }
		.column-menu { right: auto; left: 0; width: min(100%, 280px); }
    }

    @media (max-width: 900px) {
        .table-card table {
			min-width: 1100px;
        }
		.table-card table.wide-table {
			min-width: 1320px;
		}
        .table-card th:first-child,
        .table-card td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background: white;
            box-shadow: 8px 0 12px -12px rgb(15 23 42 / 45%);
        }
        .table-card th:first-child {
            z-index: 3;
            background: #f8fafc;
        }
		.search-control { gap: 8px; }
		.search-control :global(.search-scope) { flex-basis: 112px; }
        .filter-actions {
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 8px;
        }

        .filter-actions .btn {
            min-width: 0;
            min-height: 42px;
            flex: 1 1 0;
            padding: 8px 5px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

	@media (max-width: 480px) {
		.pagination {
			align-items: stretch;
			flex-direction: column;
		}
		.pagination-info,
		.pagination-actions {
			width: 100%;
		}
		.pagination-actions button {
			min-width: 0;
			flex: 1 1 0;
		}
	}
</style>
