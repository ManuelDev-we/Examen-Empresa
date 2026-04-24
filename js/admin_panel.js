async function adminRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: "same-origin",
        ...options
    });

    let payload;

    try {
        payload = await response.json();
    } catch (error) {
        throw new Error("El servidor no devolvió JSON válido. Revisa api/admin.php.");
    }

    if (!response.ok || !payload.success) {
        throw new Error(payload.message || "Error del servidor.");
    }

    return payload.data;
}

function setAdminMessage(id, text, type = "") {
    const element = document.getElementById(id);

    if (!element) {
        return;
    }

    element.textContent = text || "";
    element.style.color = type === "error"
        ? "#b94e4e"
        : type === "success"
            ? "#18744e"
            : "";
}

function renderWorkers(workers) {
    const container = document.getElementById("workersList");
    const select = document.getElementById("task_trabajador_id");

    if (!container || !select) {
        return;
    }

    container.innerHTML = "";
    select.innerHTML = '<option value="">Selecciona un trabajador</option>';

    if (!workers.length) {
        container.innerHTML = '<div class="item">Todavía no hay trabajadores en esta empresa.</div>';
        return;
    }

    workers.forEach((worker) => {
        const item = document.createElement("div");
        item.className = "item";

        item.innerHTML = `
            <div class="item-head">
                <strong>${worker.nombre_completo}</strong>
                <span class="badge">${worker.puesto}</span>
            </div>
            <div class="muted">${worker.correo}</div>
            <div class="muted">Usuario: ${worker.nombre_usuario}</div>
            <div class="muted">Teléfono: ${worker.telefono || "Sin teléfono"}</div>
            <div class="row-actions">
                ${worker.puesto === "coworker" ? `<button class="danger" type="button" data-remove="${worker.usuario_id}">Remover del equipo</button>` : ""}
            </div>
        `;

        container.appendChild(item);

        if (worker.puesto === "coworker") {
            const option = document.createElement("option");
            option.value = worker.trabajador_id;
            option.textContent = worker.nombre_completo;
            select.appendChild(option);
        }
    });

    container.querySelectorAll("[data-remove]").forEach((button) => {
        button.addEventListener("click", async () => {
            const confirmar = confirm("¿Seguro que quieres remover a este trabajador?");

            if (!confirmar) {
                return;
            }

            try {
                await adminRequest("../api/admin.php?action=remove-member", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ usuario_id: Number(button.dataset.remove) })
                });

                setAdminMessage("memberFormMessage", "Trabajador removido correctamente.", "success");
                await loadAdminDashboard();
            } catch (error) {
                setAdminMessage("memberFormMessage", error.message, "error");
            }
        });
    });
}

function renderTasks(tasks) {
    const container = document.getElementById("tasksList");

    if (!container) {
        return;
    }

    container.innerHTML = "";

    if (!tasks.length) {
        container.innerHTML = '<div class="item">Aún no se han asignado tareas.</div>';
        return;
    }

    tasks.forEach((task) => {
        const statusClass = task.entrega_estado === "hecho"
            ? "ok"
            : task.entrega_estado === "rechazado"
                ? "rejected"
                : task.entrega_estado === "entregado"
                    ? "review"
                    : "";

        const statusLabel = task.entrega_estado === "hecho"
            ? "Hecho"
            : task.entrega_estado === "rechazado"
                ? "Rechazada"
                : task.entrega_estado === "entregado"
                    ? "En revisión"
                    : "Pendiente";

        const item = document.createElement("div");
        item.className = "item";

        item.innerHTML = `
            <div class="item-head">
                <strong>${task.titulo}</strong>
                <span class="badge ${statusClass}">${statusLabel}</span>
            </div>
            <div>${task.descripcion || "Sin descripción"}</div>
            <div class="muted">Responsable: ${task.trabajador}</div>
            <div class="muted">Fecha límite: ${task.fecha_limite}</div>
            <div class="muted">Evidencia: ${task.tiene_evidencia ? "Subida" : "Sin evidencia"}</div>
            ${task.entrega_comentario ? `<div class="muted">Revisión actual: ${task.entrega_comentario}</div>` : ""}
            ${task.entrega_estado === "entregado" || task.entrega_estado === "rechazado" ? `
                <div class="row-actions">
                    <button class="primary" type="button" data-approve="${task.id}">Marcar hecho</button>
                    <button class="danger" type="button" data-reject="${task.id}">Rechazar entrega</button>
                </div>
            ` : ""}
        `;

        container.appendChild(item);
    });

    container.querySelectorAll("[data-approve]").forEach((button) => {
        button.addEventListener("click", async () => {
            try {
                await adminRequest("../api/admin.php?action=review-task", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        tarea_id: Number(button.dataset.approve),
                        estado: "hecho"
                    })
                });

                setAdminMessage("taskFormMessage", "La tarea fue marcada como hecha.", "success");
                await loadAdminDashboard();
            } catch (error) {
                setAdminMessage("taskFormMessage", error.message, "error");
            }
        });
    });

    container.querySelectorAll("[data-reject]").forEach((button) => {
        button.addEventListener("click", async () => {
            const comentario = window.prompt("Motivo del rechazo para el empleado:", "Necesita ajustes");

            if (comentario === null) {
                return;
            }

            try {
                await adminRequest("../api/admin.php?action=review-task", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        tarea_id: Number(button.dataset.reject),
                        estado: "rechazado",
                        comentario
                    })
                });

                setAdminMessage("taskFormMessage", "La entrega fue rechazada.", "success");
                await loadAdminDashboard();
            } catch (error) {
                setAdminMessage("taskFormMessage", error.message, "error");
            }
        });
    });
}

async function loadAdminDashboard() {
    try {
        const data = await adminRequest("../api/admin.php?action=dashboard");

        document.getElementById("adminWelcome").textContent = `Panel administrador: ${data.empresa.nombre}`;
        document.getElementById("adminCompany").textContent = "Gestiona contrataciones, tareas y seguimiento de esta empresa.";
        document.getElementById("statWorkers").textContent = data.resumen.trabajadores;
        document.getElementById("statTasks").textContent = data.resumen.tareas;
        document.getElementById("statPending").textContent = data.resumen.pendientes;
        document.getElementById("statDone").textContent = data.resumen.hechas;
        document.getElementById("statRejected").textContent = data.resumen.rechazadas;

        renderWorkers(data.trabajadores);
        renderTasks(data.tareas);
    } catch (error) {
        console.error("Error cargando panel administrador:", error);
        window.location.href = "../html/panel.html";
    }
}

document.getElementById("addMemberForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    setAdminMessage("memberFormMessage", "");

    const correoInput = document.getElementById("member_correo");
    const telefonoInput = document.getElementById("member_telefono");

    const payload = {
        correo: correoInput ? correoInput.value.trim() : "",
        telefono: telefonoInput ? telefonoInput.value.trim() : ""
    };

    if (!payload.correo) {
        setAdminMessage("memberFormMessage", "Debes indicar el correo del usuario existente.", "error");
        return;
    }

    try {
        const data = await adminRequest("../api/admin.php?action=add-member", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        setAdminMessage("memberFormMessage", data.message, "success");
        event.target.reset();
        await loadAdminDashboard();
    } catch (error) {
        setAdminMessage("memberFormMessage", error.message, "error");
    }
});

document.getElementById("assignTaskForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    setAdminMessage("taskFormMessage", "");

    const payload = {
        trabajador_id: Number(document.getElementById("task_trabajador_id").value),
        titulo: document.getElementById("task_titulo").value.trim(),
        descripcion: document.getElementById("task_descripcion").value.trim(),
        fecha_limite: document.getElementById("task_fecha_limite").value
    };

    if (!payload.trabajador_id || !payload.titulo || !payload.fecha_limite) {
        setAdminMessage("taskFormMessage", "Selecciona trabajador, título y fecha límite.", "error");
        return;
    }

    payload.fecha_limite = payload.fecha_limite.replace("T", " ") + ":00";

    try {
        const data = await adminRequest("../api/admin.php?action=assign-task", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        setAdminMessage("taskFormMessage", data.message, "success");
        event.target.reset();
        await loadAdminDashboard();
    } catch (error) {
        setAdminMessage("taskFormMessage", error.message, "error");
    }
});

document.getElementById("backToPanel")?.addEventListener("click", () => {
    window.location.href = "../html/panel.html";
});

document.getElementById("reloadAdmin")?.addEventListener("click", loadAdminDashboard);

document.getElementById("logoutAdmin")?.addEventListener("click", async () => {
    try {
        await adminRequest("../api/login.php?action=logout", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: "{}"
        });
    } finally {
        window.location.href = "../html/index.html";
    }
});

loadAdminDashboard();
