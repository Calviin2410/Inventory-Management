<script>
	let {
		value = $bindable(""),
		min = "",
		max = "",
		required = false,
		disabled = false,
		id = undefined,
		compact = false,
		onchange = undefined,
		ariaLabel = "Select date",
	} = $props();

	const pattern = "[0-9]{2}/[0-9]{2}/[0-9]{4}";
	let display = $state(formatDate(value));
	let lastInternalValue = value;
	let picker = $state();
	let textInput = $state();

	$effect(() => {
		if (value !== lastInternalValue) display = formatDate(value);
		lastInternalValue = value;
	});

	$effect(() => {
		if (!textInput) return;
		let message = "";
		if (value && min && value < min) message = `Date must be on or after ${formatDate(min)}.`;
		if (value && max && value > max) message = `Date must be on or before ${formatDate(max)}.`;
		textInput.setCustomValidity(message);
	});

	function formatDate(isoDate) {
		if (!isoDate) return "";
		const [year, month, day] = isoDate.split("-");
		return year && month && day ? `${day}/${month}/${year}` : "";
	}

	function formatTyping(input) {
		const digits = input.replace(/\D/g, "").slice(0, 8);
		return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4)]
			.filter(Boolean)
			.join("/");
	}

	function parseDate(input) {
		const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(input);
		if (!match) return "";
		const [, day, month, year] = match;
		const candidate = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)));
		if (
			candidate.getUTCFullYear() !== Number(year) ||
			candidate.getUTCMonth() !== Number(month) - 1 ||
			candidate.getUTCDate() !== Number(day)
		) return "";
		return `${year}-${month}-${day}`;
	}

	function updateValue(nextValue) {
		lastInternalValue = nextValue;
		value = nextValue;
	}

	function handleTyping(event) {
		display = formatTyping(event.currentTarget.value);
		updateValue(parseDate(display));
	}

	function handleSelection(event) {
		updateValue(event.currentTarget.value);
		display = formatDate(value);
		onchange?.(event);
	}

	function openPicker() {
		if (disabled) return;
		if (typeof picker?.showPicker === "function") picker.showPicker();
		else picker?.click();
	}
</script>

<div class:compact class="date-input">
	<input
		{id}
		bind:this={textInput}
		type="text"
		inputmode="numeric"
		placeholder="dd/mm/yyyy"
		value={display}
		oninput={handleTyping}
		onchange={onchange}
		{pattern}
		maxlength="10"
		{required}
		{disabled}
	/>
	<button type="button" class="calendar-button" aria-label={ariaLabel} onclick={openPicker} {disabled}>
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2v3M17 2v3M3.5 9h17M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z" /></svg>
	</button>
	<input
		class="native-picker"
		type="date"
		{min}
		{max}
		value={value}
		bind:this={picker}
		onchange={handleSelection}
		tabindex="-1"
		aria-hidden="true"
	/>
</div>

<style>
	.date-input { position: relative; width: 100%; min-width: 170px; }
	.date-input > input[type="text"] {
		width: 100%;
		box-sizing: border-box;
		min-height: 42px;
		padding: 10px 46px 10px 12px;
		border: 1px solid #ccd4e0;
		border-radius: 9px;
		outline: none;
		background: #fff;
		color: #172033;
		font: inherit;
	}
	.date-input > input[type="text"]:focus {
		border-color: #4771e8;
		box-shadow: 0 0 0 3px rgb(53 99 233 / 12%);
	}
	.calendar-button {
		position: absolute;
		top: 50%;
		right: 5px;
		display: grid;
		width: 36px;
		height: 34px;
		padding: 0;
		place-items: center;
		transform: translateY(-50%);
		border: 0;
		background: transparent;
		color: #172033;
		cursor: pointer;
	}
	.calendar-button svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
	.native-picker { position: absolute; width: 1px; height: 1px; padding: 0; opacity: 0; pointer-events: none; }
	.compact > input[type="text"] { min-width: 145px; min-height: 38px; padding-top: 8px; padding-bottom: 8px; }
	input:disabled, button:disabled { cursor: not-allowed; opacity: 0.65; }
</style>
