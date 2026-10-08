<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";

    import { api } from "$lib/api.js";
    import { user } from "$lib/stores/auth.js";

    import Nav from "$lib/Nav.svelte";
    import SkeletonTable from "$lib/SkeletonTable.svelte";
    import DateInput from "$lib/DateInput.svelte";
    import { formatDate } from "$lib/format.js";

    let invoices = $state([]);
    let filteredInvoices = $state([]);

    let loading = $state(true);
    let exporting = $state(false);
    let errorMessage = $state("");

    let fromDate = $state("");
    let toDate = $state("");
	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalInvoices = $state(0);
	let paidInvoices = $state(0);
	let unpaidInvoices = $state(0);
	let settledInvoices = $state(0);
	let unsettledInvoices = $state(0);
	const reportColumns = [
		{ key: "invoice", label: "Invoice" },
		{ key: "invoiceDate", label: "Invoice Date" },
		{ key: "customer", label: "Customer" },
		{ key: "barrel", label: "Barrel" },
		{ key: "rentalStart", label: "Rental Start" },
		{ key: "rentalEnd", label: "Rental End" },
		{ key: "settlementStatus", label: "Settlement Status" },
		{ key: "paymentStatus", label: "Payment Status" },
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
		if (fromDate) params.from_date = fromDate;
		if (toDate) params.to_date = toDate;
		return params;
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

            return items.map((item) => ({
                invoice: invoice.invoice_no ?? "",
				invoiceDate: excelDate(invoice.issued_date),
                customer: invoice.customer?.name ?? "",
                barrel: item?.barrel?.code ?? "",
                rentalStart: excelDate(item?.rental_start),
                rentalEnd: excelDate(item?.rental_end),
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
				{ key: "settlementStatus", width: 20 },
				{ key: "paymentStatus", width: 16 },
            ];

            worksheet.mergeCells("A1:H1");
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

            worksheet.mergeCells("A2:H2");
            worksheet.getCell("A2").value = `Invoice Date Period: ${fromDate || "All dates"} to ${toDate || "All dates"}`;
            worksheet.getCell("A2").font = { color: { argb: "FF475569" } };

            worksheet.mergeCells("A3:H3");
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

            worksheet.autoFilter = "A5:H5";

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
            const currentUser = await api.me();

            user.set(currentUser);

            if (currentUser?.role !== "admin") {
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

    <!-- 日期筛选 -->
    <section class="filter-card">
        <div class="filter-grid">
            <div class="field">
                <label for="fromDate"> From Invoice Date </label>

                <DateInput id="fromDate" bind:value={fromDate} max={toDate} ariaLabel="Select report from date" />
            </div>

            <div class="field">
                <label for="toDate"> To Invoice Date </label>

                <DateInput id="toDate" bind:value={toDate} min={fromDate} ariaLabel="Select report to date" />
            </div>

            <div class="filter-actions">
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

                <button
                    type="button"
                    class="btn btn-primary"
                    onclick={generateReport}
                    disabled={loading}
                >
                    Generate Report
                </button>

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
                <div class="summary-card">
                    <span> Total Invoices </span>

                    <strong>
                        {totalInvoices}
                    </strong>
                </div>

                <div class="summary-card">
                    <span> Paid </span>

                    <strong>
                        {paidInvoices}
                    </strong>
                </div>

                <div class="summary-card">
                    <span> Unpaid </span>

                    <strong>
                        {unpaidInvoices}
                    </strong>
                </div>

                <div class="summary-card">
                    <span> Settled Invoices </span>

                    <strong>
						{settledInvoices}
					</strong>
				</div>

				<div class="summary-card">
					<span> Unsettled Invoices </span>

					<strong>
						{unsettledInvoices}
                    </strong>
                </div>
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

            {#if filteredInvoices.length === 0}
                <div class="state">No records found.</div>
            {:else}
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                {#if visibleColumns.invoice}<th> Invoice </th>{/if}

								{#if visibleColumns.invoiceDate}<th> Invoice Date </th>{/if}

								{#if visibleColumns.customer}<th> Customer </th>{/if}

								{#if visibleColumns.barrel}<th> Barrel </th>{/if}

								{#if visibleColumns.rentalStart}<th> Rental Start </th>{/if}

								{#if visibleColumns.rentalEnd}<th> Rental End </th>{/if}

								{#if visibleColumns.settlementStatus}<th> Settlement Status </th>{/if}

								{#if visibleColumns.paymentStatus}<th> Payment Status </th>{/if}
                            </tr>
                        </thead>

                        <tbody>
                            {#each filteredInvoices as invoice (invoice.id)}
                                {#if invoice.items?.length}
                                    {#each invoice.items as item}
                                        <tr>
											{#if visibleColumns.invoice}
												<td><a class="invoice-link" href={`/invoices/${invoice.id}`}>{invoice.invoice_no}</a></td>
											{/if}

											{#if visibleColumns.invoiceDate}<td>{formatDate(invoice.issued_date)}</td>{/if}

											{#if visibleColumns.customer}<td>
                                                {invoice.customer?.name ?? "-"}
                                            </td>{/if}

											{#if visibleColumns.barrel}<td>
                                                {item.barrel?.code ?? "-"}
                                            </td>{/if}

											{#if visibleColumns.rentalStart}<td>
                                                {formatDate(item.rental_start)}
                                            </td>{/if}

											{#if visibleColumns.rentalEnd}<td>
                                                {formatDate(item.rental_end)}
                                            </td>{/if}

											{#if visibleColumns.settlementStatus}<td>
												<span class="settlement-badge" class:settled={invoice.settlement_status === "settled"}>
													{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}
												</span>
											</td>{/if}

											{#if visibleColumns.paymentStatus}<td>
                                                <span
                                                    class="status-badge"
                                                    class:paid={invoice.status ===
                                                        "paid"}
                                                    class:unpaid={invoice.status ===
                                                        "unpaid"}
                                                >
                                                    {invoice.status === "paid"
                                                        ? "Paid"
                                                        : "Unpaid"}
                                                </span>
											</td>{/if}
                                        </tr>
                                    {/each}
                                {:else}
                                    <tr>
										{#if visibleColumns.invoice}
											<td><a class="invoice-link" href={`/invoices/${invoice.id}`}>{invoice.invoice_no}</a></td>
										{/if}

									{#if visibleColumns.invoiceDate}<td>{formatDate(invoice.issued_date)}</td>{/if}

									{#if visibleColumns.customer}<td>
                                            {invoice.customer?.name ?? "-"}
                                        </td>{/if}

									{#if visibleColumns.barrel}<td> - </td>{/if}

									{#if visibleColumns.rentalStart}<td> - </td>{/if}

									{#if visibleColumns.rentalEnd}<td> - </td>{/if}

									{#if visibleColumns.settlementStatus}<td>
										<span class="settlement-badge" class:settled={invoice.settlement_status === "settled"}>
											{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}
										</span>
									</td>{/if}

									{#if visibleColumns.paymentStatus}<td>
                                            <span
                                                class="status-badge"
                                                class:paid={invoice.status ===
                                                    "paid"}
                                                class:unpaid={invoice.status ===
                                                    "unpaid"}
                                            >
                                                {invoice.status === "paid"
                                                    ? "Paid"
                                                    : "Unpaid"}
                                            </span>
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

        grid-template-columns:
            minmax(170px, 1fr)
            minmax(170px, 1fr)
            minmax(0, auto);

        align-items: end;

        gap: 16px;
    }

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
		min-width: 0;
		flex-wrap: wrap;
        gap: 10px;
    }

	.filter-actions .btn {
		flex: 1 1 105px;
		white-space: normal;
	}

	@media (max-width: 1280px) {
		.filter-grid {
			grid-template-columns: repeat(2, minmax(170px, 1fr));
		}

		.filter-actions {
			grid-column: 1 / -1;
		}
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
        padding: 20px;

        border: 1px solid #e5e7eb;
        border-radius: 10px;

        background: white;
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
        overflow: hidden;

        border: 1px solid #e5e7eb;
        border-radius: 9px;

        background: white;
    }

    table {
        width: 100%;

        border-collapse: collapse;
    }

    th {
        padding: 14px 16px;

        border-bottom: 1px solid #e5e7eb;

        background: #f8fafc;

        color: #64748b;

        text-align: left;

        font-size: 13px;
        font-weight: 600;
    }

    td {
        padding: 15px 16px;

        border-bottom: 1px solid #e5e7eb;

        font-size: 14px;
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

    .status-badge.paid {
        background: #ccfbf1;

        color: #0f766e;
    }

    .status-badge.unpaid {
        background: #fee2e2;

        color: #dc2626;
    }

	.settlement-badge { display: inline-flex; min-width: 82px; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 6px; background: #fff1f2; color: #be123c; font-size: 12px; font-weight: 700; }
	.settlement-badge.settled { background: #dff8ee; color: #087a55; }

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
		.section-heading { align-items: stretch; flex-direction: column; }
		.column-picker summary { width: 100%; }
		.column-menu { right: auto; left: 0; width: min(100%, 280px); }
    }

    @media (max-width: 900px) {
        .table-card {
            overflow-x: auto;
            overscroll-behavior-inline: contain;
            -webkit-overflow-scrolling: touch;
        }
        .table-card table {
			min-width: 1100px;
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
        .filter-grid {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            flex-direction: column;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
