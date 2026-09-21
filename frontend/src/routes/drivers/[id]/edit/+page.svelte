<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";
    import { page } from "$app/state";

    import { api } from "$lib/api.js";
    import Nav from "$lib/Nav.svelte";
    import SkeletonTable from "$lib/SkeletonTable.svelte";
    import { guardUnsaved } from "$lib/unsaved.js";

    let name = $state("");
    let phone = $state("");
    let status = $state("available");

    let loading = $state(true);
    let saving = $state(false);
    let errorMessage = $state("");
    let formDirty = $state(false);

    const driverId = $derived(page.params.id);

    guardUnsaved(() => formDirty);

    async function loadDriver() {
        loading = true;
        errorMessage = "";

        try {
            const driver = await api.getDriver(driverId);

            name = driver?.name ?? "";
            phone = driver?.phone ?? "";
            status = driver?.status ?? "available";
        } catch (error) {
            if (error?.status === 403) {
                errorMessage =
                    "You do not have permission to edit this driver.";
            } else if (error?.status === 404) {
                errorMessage = "Driver not found.";
            } else {
                errorMessage =
                    error instanceof Error
                        ? error.message
                        : "Unable to load driver.";
            }
        } finally {
            loading = false;
        }
    }

    async function saveDriver(event) {
        event.preventDefault();

        if (saving) {
            return;
        }

        if (!name.trim()) {
            errorMessage = "Driver name is required.";
            return;
        }

        saving = true;
        errorMessage = "";

        try {
            await api.updateDriver(driverId, {
                name: name.trim(),
                phone: phone.trim() || null,
                status,
            });

            formDirty = false;

            await goto("/drivers");
        } catch (error) {
            if (error?.status === 403) {
                errorMessage =
                    "You do not have permission to edit this driver.";
            } else if (error?.status === 422) {
                const validationErrors = error?.errors;

                errorMessage =
                    validationErrors?.name?.[0] ||
                    validationErrors?.phone?.[0] ||
                    validationErrors?.status?.[0] ||
                    "Please check the driver details.";
            } else {
                errorMessage =
                    error instanceof Error
                        ? error.message
                        : "Unable to update driver.";
            }
        } finally {
            saving = false;
        }
    }

    function cancelEdit() {
        goto("/drivers");
    }

    onMount(loadDriver);
</script>

<Nav />

<main class="app-page">
    <header class="page-heading">
        <div>
            <p class="eyebrow">DRIVERS</p>

            <h1>Edit driver</h1>

            <p>
                Update the driver’s contact information and availability
                status.
            </p>
        </div>

        <button
            type="button"
            class="btn"
            onclick={cancelEdit}
        >
            Back
        </button>
    </header>

    {#if loading}
        <div class="state">
            <SkeletonTable rows={3} columns={2} />
        </div>
    {:else}
        <section class="form-card">
            <form
                oninput={() => {
                    formDirty = true;
                }}
                onsubmit={saveDriver}
            >
                {#if errorMessage}
                    <div class="error-message" role="alert">
                        {errorMessage}
                    </div>
                {/if}

                <div class="field">
                    <label for="driver-name">
                        Driver Name
                    </label>

                    <input
                        id="driver-name"
                        class="control"
                        type="text"
                        placeholder="Example: Bryan"
                        autocomplete="name"
                        bind:value={name}
                        disabled={saving}
                        required
                    />
                </div>

                <div class="field">
                    <label for="driver-phone">
                        Phone
                    </label>

                    <input
                        id="driver-phone"
                        class="control"
                        type="tel"
                        placeholder="Example: 012-345 6789"
                        autocomplete="tel"
                        bind:value={phone}
                        disabled={saving}
                    />
                </div>

                <div class="field">
                    <label for="driver-status">
                        Status
                    </label>

                    <select
                        id="driver-status"
                        class="control"
                        bind:value={status}
                        disabled={saving}
                    >
                        <option value="available">
                            Available
                        </option>

                        <option value="unavailable">
                            Unavailable
                        </option>
                    </select>

                    <p class="field-help">
                        Unavailable drivers cannot be assigned to new
                        invoices.
                    </p>
                </div>

                <div class="form-actions">
                    <button
                        type="button"
                        class="btn"
                        onclick={cancelEdit}
                        disabled={saving}
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        disabled={saving}
                    >
                        {saving ? "Saving…" : "Save changes"}
                    </button>
                </div>
            </form>
        </section>
    {/if}
</main>

<style>
    .form-card {
        max-width: 650px;
        padding: 28px;

        border: 1px solid #e5e7eb;
        border-radius: 10px;

        background: white;
    }

    form {
        display: flex;
        flex-direction: column;

        gap: 22px;
    }

    .field {
        display: flex;
        flex-direction: column;

        gap: 8px;
    }

    .field label {
        color: #374151;

        font-size: 14px;
        font-weight: 600;
    }

    .field .control {
        width: 100%;
    }

    .field-help {
        margin: 0;

        color: #64748b;

        font-size: 13px;
        line-height: 1.5;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;

        gap: 10px;

        padding-top: 8px;
    }

    .error-message {
        padding: 12px 14px;

        border: 1px solid #fecaca;
        border-radius: 8px;

        background: #fef2f2;
        color: #b42318;

        font-size: 14px;
    }

    .state {
        padding: 30px 0;

        color: #64748b;
    }

    @media (max-width: 760px) {
        .form-card {
            padding: 20px;
        }

        .form-actions {
            flex-direction: column-reverse;
        }

        .form-actions .btn {
            width: 100%;
        }
    }
</style>