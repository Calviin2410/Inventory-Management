<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";

    import { api } from "$lib/api.js";
    import { user } from "$lib/stores/auth.js";

    import Nav from "$lib/Nav.svelte";

    let invoices = $state([]);
    let filteredInvoices = $state([]);

    let loading = $state(true);
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

    onMount(async () => {
        /*
         * Layout 会先恢复 user，
         * 所以这里让页面等一个 tick。
         */
        await Promise.resolve();

        if (!isAdmin) {
            goto("/dashboard");
            return;
        }

        await loadReports();
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
                    class="btn btn-primary"
                    onclick={generateReport}
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
        <div class="state">Loading report...</div>
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
                                                {item.rental_start ?? "-"}
                                            </td>

                                            <td>
                                                {item.rental_end ?? "-"}
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
