<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";

    import { api } from "$lib/api.js";
    import { user } from "$lib/stores/auth.js";

    import Nav from "$lib/Nav.svelte";
    import SkeletonTable from "$lib/SkeletonTable.svelte";
    import { formatDate } from "$lib/format.js";

    let invoices = $state([]);
    let filteredInvoices = $state([]);

    let loading = $state(true);
    let exporting = $state(false);
    let errorMessage = $state("");

    let fromDate = $state("");
    let toDate = $state("");

    let isAdmin = $derived($user?.role === "admin");

    // 统计
    let totalInvoices = $derived(filteredInvoices.length);

    let paidInvoices = $derived(
        filteredInvoices.filter((invoice) => invoice.status === "paid").length,
    );

    let unpaidInvoices = $derived(
        filteredInvoices.filter((invoice) => invoice.status === "unpaid")
            .length,
    );

    let totalCustomers = $derived(
        new Set(
            filteredInvoices
                .map((invoice) => invoice.customer_id)
                .filter(Boolean),
        ).size,
    );

    async function loadReports(params = {}) {
        loading = true;
        errorMessage = "";

        try {
            const result = await api.getRentalReport(params);

            invoices = Array.isArray(result) ? result : [];

            filteredInvoices = invoices;
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
            errorMessage = "From Date cannot be later than To Date.";

            return;
        }

        const params = {};

        if (fromDate) {
            params.from_date = fromDate;
        }

        if (toDate) {
            params.to_date = toDate;
        }

        await loadReports(params);
    }

    async function clearFilter() {
        fromDate = "";
        toDate = "";

        await loadReports();
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

    function exportRows() {
        return filteredInvoices.flatMap((invoice) => {
            const items = invoice.items?.length ? invoice.items : [null];

            return items.map((item) => ({
                invoice: invoice.invoice_no ?? "",
                customer: invoice.customer?.name ?? "",
                barrel: item?.barrel?.code ?? "",
                rentalStart: excelDate(item?.rental_start),
                rentalEnd: excelDate(item?.rental_end),
                status: invoice.status === "paid" ? "Paid" : "Unpaid",
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
            const ExcelJS = (await import("exceljs")).default;
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet("Rental Report", {
                views: [{ state: "frozen", ySplit: 6 }],
            });

            workbook.creator = "Inventory Management System";
            workbook.created = new Date();

            worksheet.columns = [
                { key: "invoice", width: 18 },
                { key: "customer", width: 26 },
                { key: "barrel", width: 16 },
                { key: "rentalStart", width: 18 },
                { key: "rentalEnd", width: 18 },
                { key: "status", width: 14 },
            ];

            worksheet.mergeCells("A1:F1");
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

            worksheet.mergeCells("A2:F2");
            worksheet.getCell("A2").value = `Period: ${fromDate || "All dates"} to ${toDate || "All dates"}`;
            worksheet.getCell("A2").font = { color: { argb: "FF475569" } };

            worksheet.mergeCells("A3:F3");
            worksheet.getCell("A3").value =
                `Total Invoices: ${totalInvoices}   |   Paid: ${paidInvoices}   |   Unpaid: ${unpaidInvoices}   |   Customers: ${totalCustomers}`;
            worksheet.getCell("A3").font = { bold: true };

            const headerRow = worksheet.getRow(5);
            headerRow.values = [
                "Invoice",
                "Customer",
                "Barrel",
                "Rental Start",
                "Rental End",
                "Status",
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

            for (const rowData of exportRows()) {
                const row = worksheet.addRow(rowData);
                row.getCell("rentalStart").numFmt = "dd mmm yyyy";
                row.getCell("rentalEnd").numFmt = "dd mmm yyyy";

                const statusCell = row.getCell("status");
                statusCell.font = {
                    bold: true,
                    color: {
                        argb:
                            rowData.status === "Paid"
                                ? "FF047857"
                                : "FFDC2626",
                    },
                };
            }

            worksheet.autoFilter = "A5:F5";

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

            await loadReports();
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
                Review rental activity within a selected date range.
            </p>
        </div>
    </header>

    <!-- 日期筛选 -->
    <section class="filter-card">
        <div class="filter-grid">
            <div class="field">
                <label for="fromDate"> From Date </label>

                <input
                    id="fromDate"
                    class="control"
                    type="date"
                    bind:value={fromDate}
                />
            </div>

            <div class="field">
                <label for="toDate"> To Date </label>

                <input
                    id="toDate"
                    class="control"
                    type="date"
                    bind:value={toDate}
                />
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
                    <span> Customers </span>

                    <strong>
                        {totalCustomers}
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
            </div>

            {#if filteredInvoices.length === 0}
                <div class="state">No records found.</div>
            {:else}
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th> Invoice </th>

                                <th> Customer </th>

                                <th> Barrel </th>

                                <th> Rental Start </th>

                                <th> Rental End </th>

                                <th> Status </th>
                            </tr>
                        </thead>

                        <tbody>
                            {#each filteredInvoices as invoice (invoice.id)}
                                {#if invoice.items?.length}
                                    {#each invoice.items as item}
                                        <tr>
                                            <td class="invoice-number">
                                                {invoice.invoice_no}
                                            </td>

                                            <td>
                                                {invoice.customer?.name ?? "-"}
                                            </td>

                                            <td>
                                                {item.barrel?.code ?? "-"}
                                            </td>

                                            <td>
                                                {formatDate(item.rental_start)}
                                            </td>

                                            <td>
                                                {formatDate(item.rental_end)}
                                            </td>

                                            <td>
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
                                            </td>
                                        </tr>
                                    {/each}
                                {:else}
                                    <tr>
                                        <td class="invoice-number">
                                            {invoice.invoice_no}
                                        </td>

                                        <td>
                                            {invoice.customer?.name ?? "-"}
                                        </td>

                                        <td> - </td>

                                        <td> - </td>

                                        <td> - </td>

                                        <td>
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
                                        </td>
                                    </tr>
                                {/if}
                            {/each}
                        </tbody>
                    </table>
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
            1fr
            1fr
            auto;

        align-items: end;

        gap: 16px;
    }

    .field {
        display: flex;
        flex-direction: column;

        gap: 7px;
    }

    .field label {
        color: #374151;

        font-size: 13px;
        font-weight: 600;
    }

    .field .control {
        width: 100%;
    }

    .filter-actions {
        display: flex;

        gap: 10px;
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

        grid-template-columns: repeat(4, minmax(0, 1fr));

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

    .invoice-number {
        font-weight: 600;
    }

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
    }

    @media (max-width: 700px) {
        .table-card {
            overflow-x: auto;
            overscroll-behavior-inline: contain;
            -webkit-overflow-scrolling: touch;
        }
        .table-card table {
            min-width: 760px;
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
