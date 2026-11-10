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

	document.addEventListener("DOMContentLoaded", () => {

    // collapsible form sections
    document.querySelectorAll(".form-section .section-toggle").forEach(btn => {
      btn.addEventListener("click", () => {
					const content = btn.nextElementSibling;
					const isOpen = content.style.display === "block";
					content.style.display = isOpen ? "none" : "block";
					btn.classList.toggle("open", !isOpen);
			});
    });

		// keep Send-to / Email section open when email is requested
    const sendCheckbox = document.querySelector("#send");

    if (sendCheckbox) {
        const sendSection = sendCheckbox.closest(".form-section");
        const btn = sendSection.querySelector(".section-toggle");
        const content = sendSection.querySelector(".section-content");

        function setSendSectionState() {
            content.style.display = sendCheckbox.checked ? "block" : "none";
            btn.classList.toggle("open", sendCheckbox.checked);
        }

        // Set initial state from PHP-generated checkbox state
        setSendSectionState();

        // Change state whenever checkbox is checked/unchecked
        sendCheckbox.addEventListener("change", setSendSectionState);
    }


    // auto-collapse player section once selected
    const playerSection = document.querySelector("#playerSection");
    if (playerSection && playerSection.dataset.selected === "yes") {
			const btn = playerSection.querySelector(".section-toggle");
			const content = playerSection.querySelector(".section-content");
			content.style.display = "none";
			btn.classList.remove("open");
    }
	});
	
