document.addEventListener('DOMContentLoaded', function () {
	let deleteTarget = null;
	const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
	const confirmBtn = document.getElementById('confirmDeleteBtn');

	const filters = {
		url: document.getElementById('filter-url'),
		status: document.getElementById('filter-status'),
		malwareVT: document.getElementById('filter-malware-vt'),
		malwareSucuri: document.getElementById('filter-malware-sucuri')
	};

	const rows = document.querySelectorAll('table tbody tr');

	function applyFilters() {
		const values = {
			url: filters.url.value.toLowerCase(),
			status: filters.status.value,
			malwareVT: filters.malwareVT.value,
			malwareSucuri: filters.malwareSucuri.value
		};

		rows.forEach(row => {
			const url = row.cells[0].textContent.toLowerCase();
			const status = row.cells[1].textContent.trim();
			const vt = row.cells[3].textContent.trim();
			const sucuri = row.cells[4].textContent.trim();

			const show =
				url.includes(values.url) &&
				(!values.status || status === values.status) &&
				(!values.malwareVT || vt === values.malwareVT) &&
				(!values.malwareSucuri || sucuri === values.malwareSucuri);

			row.style.display = show ? '' : 'none';
		});
	}

	// Restaurar y escuchar filtros
	Object.entries(filters).forEach(([key, input]) => {
		const saved = localStorage.getItem(`filter_${key}`);
		if (saved !== null) {
			input.value = saved;
		}
		input.addEventListener('input', () => {
			localStorage.setItem(`filter_${key}`, input.value);
			applyFilters();
		});
	});

	document.getElementById('reset-filters').addEventListener('click', () => {
		Object.entries(filters).forEach(([key, input]) => {
			input.value = '';
			localStorage.removeItem(`filter_${key}`);
		});
		applyFilters();
	});

	applyFilters();

	// Delegación para analizar y eliminar
	const table = document.getElementById('websites-table');
	if (table) {
		table.addEventListener('click', async function (e) {
			// Análisis
			const analyzeButton = e.target.closest('.analyze-btn');
			if (analyzeButton) {
				const websiteId = analyzeButton.getAttribute('data-id');
				const url = analyzeButton.getAttribute('data-url');

				analyzeButton.disabled = true;
				const originalText = analyzeButton.textContent;
				analyzeButton.textContent = 'Analizando...';

				try {
					const response = await fetch('index.php?route=/websites/check-ajax', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ website_id: websiteId })
					});
					const data = await response.json();

					if (data.success && data.data) {
						const row = analyzeButton.closest('tr');
						const analysis = data.data;

						row.cells[1].innerHTML = `<span class="badge ${+analysis.is_online ? 'bg-success' : 'bg-danger'}">${+analysis.is_online ? 'OK' : 'Caída'}</span>`;

						// Último OK
						const lastOkFormatted = analysis.last_online_ok
							? (() => {
								const d = new Date(analysis.last_online_ok);
								return `${d.toLocaleDateString('es-ES')}<br>${d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}`;
							})()
							: 'Nunca';
						row.cells[2].innerHTML = `<span class="text-nowrap">${lastOkFormatted}</span>`;

						// Malware VT
						let vtValue = analysis.has_malware_virustotal;
						let vtHtml;
						if (vtValue === -1 || vtValue === '-1') {
							vtHtml = '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Se ha excedido la cuota de VirusTotal. Inténtalo más tarde.">No info</span>';
						} else {
							vtHtml = `<span class="badge ${+vtValue ? 'bg-danger' : 'bg-success'}">${+vtValue ? 'Sí' : 'No'}</span>`;
						}
						row.cells[3].innerHTML = vtHtml;

						// Malware Sucuri
						row.cells[4].innerHTML = `<span class="badge ${+analysis.has_malware_sucuri ? 'bg-danger' : 'bg-success'}">${+analysis.has_malware_sucuri ? 'Sí' : 'No'}</span>`;

						// Última vez limpia
						const lastCleanFormatted = analysis.last_clean
						? (() => {
							const d = new Date(analysis.last_clean);
							return `${d.toLocaleDateString('es-ES')}<br>${d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}`;
						})()
						: 'Nunca';

						row.cells[5].innerHTML = `<span class="text-nowrap">${lastCleanFormatted}</span>`;

						// Último chequeo
						const lastCheckFormatted = analysis.created_at
						? (() => {
							const d = new Date(analysis.created_at);
							return `${d.toLocaleDateString('es-ES')}<br>${d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}`;
						})()
						: 'Nunca';
						row.cells[6].innerHTML = `<span class="text-nowrap">${lastCheckFormatted}</span>`;
					
						// Otros datos (SSL, Bad DNS, Error PHP)
						row.cells[7].innerHTML = `
							<button class="btn btn-sm btn-info other-data-btn" 
								data-ssl="${analysis.have_ssl}"
								data-bad-dns="${analysis.bad_dns}"
								data-error-php="${analysis.error_php}"
								data-ip="${analysis.ip || ''}"
								data-hostname="${analysis.hostname || ''}"
								data-org="${analysis.asn || ''}"
								data-bs-toggle="modal" data-bs-target="#otherDataModal"
								title="Ver otros datos">
								<i class="fas fa-eye"></i>
							</button>
						`;

						// Highlight
						row.classList.add('highlight');
						setTimeout(() => row.classList.remove('highlight'), 3000);
					}

					showToast(data.success ? 'success' : 'danger', data.message + ': ' + url);
				} catch (error) {
					showToast('danger', 'Error al conectar con el servidor');
				}

				analyzeButton.textContent = originalText;
				analyzeButton.disabled = false;
				return;
			}

			// Eliminar
			const deleteButton = e.target.closest('.delete-btn');
			if (deleteButton) {
				const row = deleteButton.closest('tr');
				const websiteId = deleteButton.getAttribute('data-id');
				const urlText = row.querySelector('td')?.textContent.trim() || '';

				// Guardar info temporal para usarla cuando se confirme
				deleteTarget = { row, websiteId };
				document.getElementById('confirm-url-preview').textContent = urlText;

				confirmModal.show();
			}
		});
	}

	// Confirmación desde el modal
	confirmBtn.addEventListener('click', async () => {
		if (!deleteTarget) return;

		const { websiteId, row } = deleteTarget;
		confirmBtn.disabled = true;

		try {
			const res = await fetch('index.php?route=/websites/delete-ajax', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ website_id: websiteId })
			});
			const result = await res.json();

			if (result.success) {
				row.classList.add('fade-out');
				setTimeout(() => {
					row.remove();
					applyFilters();
				}, 400);
			}

			showToast(result.success ? 'success' : 'danger', result.message);
		} catch (err) {
			showToast('danger', 'Error al eliminar el sitio');
		}

		confirmBtn.disabled = false;
		confirmModal.hide();
		deleteTarget = null;
	});
	
	let pendingScrollRow = null; // variable global temporal

	document.getElementById('insertUrlBtn').addEventListener('click', async () => {
		const input = document.getElementById('new-url');
		const url = input.value.trim();
		const pattern = /^(www\.)?[a-z0-9\-]+\.[a-z]{2,}$/i;

		if (!pattern.test(url)) {
			showToast('danger', 'Formato inválido. Ej: www.ejemplo.com o ejemplo.es');
			return;
		}

		const insertBtn = document.getElementById('insertUrlBtn');
		insertBtn.disabled = true;
		const originalText = insertBtn.innerHTML;
		insertBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Insertando...`;

		try {
			const res = await fetch('index.php?route=/websites/insert-ajax', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ url })
			});
			const result = await res.json();

			if (result.success) {
				showToast('success', result.message);
				input.value = '';

				const newRow = addRowToTable(result.data);
				pendingScrollRow = newRow; // se usará más abajo

				document.getElementById('insertUrlModal').querySelector('.btn-close').click();
				applyFilters();
			} else {
				showToast('danger', result.message);
			}
		} catch (err) {
			showToast('danger', 'Error al insertar URL');
		}

		insertBtn.disabled = false;
		insertBtn.innerHTML = originalText;
	});

	// Espera al cierre total del modal para hacer scroll
	document.getElementById('insertUrlModal').addEventListener('hidden.bs.modal', () => {
		if (pendingScrollRow) {
			setTimeout(() => {
				pendingScrollRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
				pendingScrollRow = null;
			}, 100); // 100ms de retraso para asegurar que el DOM está listo
		}
	});

	// Lógica para mostrar el modal de Otros Datos
	document.addEventListener('click', function(e) {
		const btn = e.target.closest('.other-data-btn');
		if (btn) {
			// SSL
			let ssl = btn.getAttribute('data-ssl');
			let sslHtml = '';
			if (ssl == '1') sslHtml = '<span class="badge bg-success">Sí</span>';
			else if (ssl == '2') sslHtml = '<span class="badge bg-warning text-dark">No info</span>';
			else sslHtml = '<span class="badge bg-danger">No</span>';
			document.getElementById('modal-ssl').innerHTML = sslHtml;
			// Bad DNS
			let badDns = btn.getAttribute('data-bad-dns');
			let badDnsHtml = badDns == '1' ? '<span class="badge bg-danger">Sí</span>' : '<span class="badge bg-success">No</span>';
			document.getElementById('modal-bad-dns').innerHTML = badDnsHtml;
			// Error PHP
			let errorPhp = btn.getAttribute('data-error-php');
			let errorPhpHtml = errorPhp == '1' ? '<span class="badge bg-danger">Sí</span>' : '<span class="badge bg-success">No</span>';
			document.getElementById('modal-error-php').innerHTML = errorPhpHtml;
			// IP, Hostname, Org (ASN)
			document.getElementById('modal-ip').textContent = btn.getAttribute('data-ip') || '-';
			document.getElementById('modal-hostname').textContent = btn.getAttribute('data-hostname') || '-';
			document.getElementById('modal-org').textContent = btn.getAttribute('data-org') || '-';
		}
	});

	document.getElementById('showDnsHistoryBtn').addEventListener('click', function() {
		// Ocultar el modal de 'Otros datos'
		const otherDataModalEl = document.getElementById('otherDataModal');
		const otherDataModal = bootstrap.Modal.getInstance(otherDataModalEl);
		if (otherDataModal) otherDataModal.hide();
		// Obtener el ID del sitio de la fila seleccionada (usando el botón de otros datos)
		const lastBtn = document.querySelector('.other-data-btn[data-bs-toggle="modal"][aria-expanded="true"], .other-data-btn.active');
		let siteId = null;
		if (lastBtn) {
			// Buscar el id en la fila
			const tr = lastBtn.closest('tr');
			if (tr) {
				siteId = tr.querySelector('.analyze-btn')?.getAttribute('data-id');
			}
		}
		// Alternativamente, buscar el id del último modal abierto
		if (!siteId) {
			// Buscar el id de la última fila seleccionada
			const modals = document.querySelectorAll('.other-data-btn');
			for (const btn of modals) {
				if (btn === window._lastOtherDataBtn) {
					const tr = btn.closest('tr');
					siteId = tr?.querySelector('.analyze-btn')?.getAttribute('data-id');
					break;
				}
			}
		}
		// Si no se encuentra, mostrar error
		if (!siteId) {
			document.getElementById('dns-history-list').innerHTML = '<li class="list-group-item text-danger">No se pudo determinar el sitio.</li>';
			new bootstrap.Modal(document.getElementById('dnsHistoryModal')).show();
			return;
		}
		// Mostrar modal y spinner
		document.getElementById('dns-history-list').innerHTML = '<li class="list-group-item text-center text-muted">Cargando historial...</li>';
		const dnsHistoryModal = new bootstrap.Modal(document.getElementById('dnsHistoryModal'));
		dnsHistoryModal.show();
		fetch(`index.php?route=/websites/dns-history&website_id=${siteId}`)
			.then(res => res.json())
			.then(data => {
				if (!data.success || !Array.isArray(data.history) || data.history.length === 0) {
					document.getElementById('dns-history-list').innerHTML = '<li class="list-group-item text-muted">Sin historial de cambios.</li>';
					return;
				}
				document.getElementById('dns-history-list').innerHTML = data.history.map(item =>
					`<li class="list-group-item">
						<strong>${item.created_at}</strong><br>
						IP: <code>${item.ip || '-'}</code><br>
						Hostname: <code>${item.hostname || '-'}</code><br>
						Org: <code>${item.asn || '-'}</code>
					</li>`
				).join('');
			})
			.catch(() => {
				document.getElementById('dns-history-list').innerHTML = '<li class="list-group-item text-danger">Error al obtener el historial.</li>';
			});
		// Al cerrar el modal de historial, volver a mostrar el de otros datos
		const dnsHistoryModalEl = document.getElementById('dnsHistoryModal');
		dnsHistoryModalEl.addEventListener('hidden.bs.modal', function handler() {
			otherDataModal.show();
			dnsHistoryModalEl.removeEventListener('hidden.bs.modal', handler);
		});
	});
	// Guardar el último botón de otros datos pulsado
	// (para saber a qué sitio corresponde el historial)
	document.addEventListener('click', function(e) {
		const btn = e.target.closest('.other-data-btn');
		if (btn) {
			window._lastOtherDataBtn = btn;
		}
	});

	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
	tooltipTriggerList.forEach(function (tooltipTriggerEl) {
		new bootstrap.Tooltip(tooltipTriggerEl);
	});
});

// Toast flotante
function showToast(type, message) {
	const container = document.getElementById('toast-container');

	const toast = document.createElement('div');
	toast.className = `toast align-items-center text-bg-${type} border-0 show mb-2`;
	toast.setAttribute('role', 'alert');
	toast.setAttribute('aria-live', 'assertive');
	toast.setAttribute('aria-atomic', 'true');

	toast.innerHTML = `
		<div class="d-flex">
			<div class="toast-body">${message}</div>
			<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
		</div>
	`;

	container.appendChild(toast);

	const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
	bsToast.show();

	setTimeout(() => {
		toast.remove();
	}, 5500);
}

// Función auxiliar para añadir una fila (URL)
function addRowToTable(data) {
	const tbody = document.querySelector('#websites-table tbody');
	const newRow = document.createElement('tr');

	const status = +data.is_online ? 'OK' : 'Caída';
	const statusClass = +data.is_online ? 'bg-success' : 'bg-danger';
	const vtClass = +data.has_malware_virustotal ? 'bg-danger' : 'bg-success';
	const sucuriClass = +data.has_malware_sucuri ? 'bg-danger' : 'bg-success';

	const formatDateTime = (datetime) => {
		if (!datetime) return 'Nunca';
		const dateObj = new Date(datetime);
		const date = dateObj.toLocaleDateString('es-ES');
		const time = dateObj.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
		return `${date}<br>${time}`;
	};

	const lastOk = data.last_online_ok ? formatDateTime(data.last_online_ok) : 'Nunca';
	const lastCheck = data.created_at ? formatDateTime(data.created_at) : 'Nunca';
	const lastClean = data.last_clean ? formatDateTime(data.last_clean) : 'Nunca';

	let vtHtml;
	if (data.has_malware_virustotal === -1 || data.has_malware_virustotal === '-1') {
		vtHtml = '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="Se ha excedido la cuota de VirusTotal. Inténtalo más tarde.">No info</span>';
	} else {
		vtHtml = `<span class="badge ${vtClass}">${+data.has_malware_virustotal ? 'Sí' : 'No'}</span>`;
	}

	newRow.innerHTML = `
		<td>${data.url}</td>
		<td><span class="badge ${statusClass}">${status}</span></td>
		<td class="text-nowrap">${lastOk}</td>
		<td>${vtHtml}</td>
		<td><span class="badge ${sucuriClass}">${+data.has_malware_sucuri ? 'Sí' : 'No'}</span></td>
		<td class="text-nowrap">${lastClean}</td>
		<td class="text-nowrap">${lastCheck}</td>
		<td>
			<button class="btn btn-sm btn-info other-data-btn" 
				data-ssl="${data.have_ssl}"
				data-bad-dns="${data.bad_dns}"
				data-error-php="${data.error_php}"
				data-ip="${data.ip || ''}"
				data-hostname="${data.hostname || ''}"
				data-org="${data.asn || ''}"
				data-bs-toggle="modal" data-bs-target="#otherDataModal"
				title="Ver otros datos">
				<i class="fas fa-eye"></i>
			</button>
		</td>
		<td>
			<button class="btn btn-sm btn-primary analyze-btn" data-id="${data.id}" data-url="${data.url}">Analizar</button>
			<button class="btn btn-sm btn-danger delete-btn" data-id="${data.id}">
				<i class="fas fa-trash-alt"></i>
			</button>
		</td>
	`;

	// Insertar alfabéticamente según la URL
	const rows = [...tbody.querySelectorAll('tr')];
	const urlLower = data.url.toLowerCase();
	let inserted = false;

	for (const row of rows) {
		const rowUrl = row.cells[0].textContent.toLowerCase();
		if (urlLower < rowUrl) {
			tbody.insertBefore(newRow, row);
			inserted = true;
			break;
		}
	}

	if (!inserted) {
		tbody.appendChild(newRow);
	}

	// Highlight visual + scroll
	newRow.classList.add('highlight');
	// newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
	setTimeout(() => newRow.classList.remove('highlight'), 3000);

	return newRow;
}