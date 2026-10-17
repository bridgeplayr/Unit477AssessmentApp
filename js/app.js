<script>
	/* Sortable columns */
	document.addEventListener("DOMContentLoaded", () => {
		document.querySelectorAll("th[data-sort]").forEach(th => {
			th.addEventListener("click", () => {
				const table = th.closest("table");
				const tbody = table.querySelector("tbody");
				const colIndex = Array.from(th.parentNode.children).indexOf(th);
				const type = th.dataset.sort;
				const rows = Array.from(tbody.querySelectorAll("tr"));

				const asc = th.classList.toggle("asc");
				th.classList.toggle("desc", !asc);

				rows.sort((a, b) => {
					let A = a.children[colIndex].innerText.trim();
					let B = b.children[colIndex].innerText.trim();

					if (type === "num") {
						A = parseFloat(A) || 0;
						B = parseFloat(B) || 0;
						return asc ? A - B : B - A;
					}

					// text sort
					return asc
						? A.localeCompare(B)
						: B.localeCompare(A);
				});

				rows.forEach(r => tbody.appendChild(r));
			});
		});
	});

	/* Accordion (collapse) columns */
	document.addEventListener("DOMContentLoaded", () => {
		document.querySelectorAll("th.toggle").forEach(btn => {
			btn.addEventListener("click", () => {
				const group = btn.dataset.target;
				const cells = document.querySelectorAll("." + group);

				btn.classList.toggle("open");

				cells.forEach(cell => {
					cell.classList.toggle("hide-col");
				});
			});
		});
	});
</script>

