document.addEventListener("DOMContentLoaded", function () {
  // Load maintenance table data
  fetchMaintenanceData();

  // Select all checkbox functionality
  document.getElementById("selectAll").addEventListener("change", function () {
    const checkboxes = document.querySelectorAll("#maintenanceBody input[type='checkbox']");
    checkboxes.forEach(cb => cb.checked = this.checked);
    toggleDisposalButton();
  });

  // Disposal request button
  document.getElementById("sendDisposalRequest").addEventListener("click", function () {
    const selectedItems = getSelectedItems();
    if (selectedItems.length > 0) {
      if (selectedItems.length === 1) {
        showDisposalReasonPopup(selectedItems);
      } else {
        // Store data and go directly to form
        localStorage.setItem('selectedItemsForDisposal', JSON.stringify(selectedItems));
        localStorage.setItem('disposalReason', ''); // Empty reason for multiple items
        window.location.href = 'condemnationForm.html';
      }
    } else {
      alert("Please select at least one item to send a disposal request.");
    }
  });

  // Show alerts button
  document.getElementById("showAlerts").addEventListener("click", loadAlerts);
});

function fetchMaintenanceData() {
  fetch("display_maintenance_table.php")
    .then(res => res.json())
    .then(data => populateTable("maintenanceBody", data, true))
    .catch(err => {
      console.error("Error loading maintenance table:", err);
      alert("Failed to load maintenance data. Please try again.");
    });
}

function loadAlerts() {
  fetch("display_alerts_table.php")
    .then(res => res.json())
    .then(data => {
      document.getElementById("alertsTable").style.display = "table";
      populateTable("alertsBody", data, true, true);
    })
    .catch(err => {
      console.error("Error loading alerts table:", err);
      alert("Failed to load alerts. Please try again.");
    });
}

function populateTable(tbodyId, data, includeAction, isAlert = false) {
  const tbody = document.getElementById(tbodyId);
  tbody.innerHTML = "";
 
  if (data.length === 0) {
    const tr = document.createElement("tr");
    const td = document.createElement("td");
    td.colSpan = isAlert ? 7 : 8;
    td.textContent = "No records found";
    td.style.textAlign = "center";
    tr.appendChild(td);
    tbody.appendChild(tr);
    return;
  }

  data.forEach((row) => {
    const tr = document.createElement("tr");
   
    if (!isAlert) {
      const checkboxTd = document.createElement("td");
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.dataset.srNo = row.sr_no;
      checkbox.dataset.labId = row.lab_id;
      checkbox.dataset.itemName = row.name_of_the_item;
      checkbox.dataset.date = row.date;
      checkbox.dataset.lastMaintenance = row.last_maintenance;
      checkbox.dataset.maintenanceDue = row.maintenance_due;
      checkbox.dataset.serviceProvider = row.service_provider;
      checkbox.addEventListener("change", toggleDisposalButton);
      checkboxTd.appendChild(checkbox);
      tr.appendChild(checkboxTd);
    }

    const columns = [row.lab_id, row.name_of_the_item, row.date,
                    formatDate(row.last_maintenance),
                    formatDate(row.maintenance_due),
                    row.service_provider || "-"];
   
    columns.forEach((val) => {
      const td = document.createElement("td");
      td.textContent = val;
      tr.appendChild(td);
    });

    if (includeAction) {
      const actionTd = document.createElement("td");
      const editBtn = document.createElement("button");
      editBtn.textContent = "Edit";
      editBtn.className = "edit-btn";
      editBtn.onclick = () => openModal(row, isAlert);
      actionTd.appendChild(editBtn);
      tr.appendChild(actionTd);
    }
   
    tbody.appendChild(tr);
  });
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return isNaN(date) ? dateString : date.toISOString().split('T')[0];
}

function toggleDisposalButton() {
  const selectedItems = getSelectedItems();
  const disposalBtn = document.getElementById("sendDisposalRequest");
  disposalBtn.style.display = selectedItems.length > 0 ? "inline-block" : "none";
}

function getSelectedItems() {
  const checkboxes = document.querySelectorAll("#maintenanceBody input[type='checkbox']:checked");
  return Array.from(checkboxes).map(cb => ({
    sr_no: cb.dataset.srNo,
    lab_id: cb.dataset.labId,
    name_of_the_item: cb.dataset.itemName,
    date: cb.dataset.date,
    last_maintenance: cb.dataset.lastMaintenance,
    maintenance_due: cb.dataset.maintenanceDue,
    service_provider: cb.dataset.serviceProvider
  }));
}

function showDisposalReasonPopup(selectedItems) {
  // Create modal overlay
  const modal = document.createElement('div');
  modal.id = 'disposalReasonModal';
  modal.style.position = 'fixed';
  modal.style.top = '0';
  modal.style.left = '0';
  modal.style.width = '100%';
  modal.style.height = '100%';
  modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
  modal.style.display = 'flex';
  modal.style.justifyContent = 'center';
  modal.style.alignItems = 'center';
  modal.style.zIndex = '1000';

  // Modal content
  modal.innerHTML = `
    <div style="background: white; padding: 20px; border-radius: 5px; width: 500px; max-width: 90%;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">Disposal Request</h2>
        <span style="cursor: pointer; font-size: 24px;" onclick="document.getElementById('disposalReasonModal').remove()">×</span>
      </div>
      <p><strong>Selected Item:</strong> ${selectedItems[0].name_of_the_item}</p>
      <div style="margin: 15px 0;">
        <label style="display: block; margin-bottom: 5px; font-weight: bold;">Reason for Disposal:</label>
        <textarea id="disposalReasonText" style="width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px;" rows="5" placeholder="Enter reason for disposal..." required></textarea>
      </div>
      <div style="text-align: right;">
        <button id="cancelDisposalBtn" style="padding: 8px 15px; margin-right: 10px; background: #f0f0f0; border: 1px solid #ddd;">Cancel</button>
        <button id="confirmDisposalBtn" style="padding: 8px 15px; background: #4CAF50; color: white; border: none;">Submit</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Add event listeners
  document.getElementById('confirmDisposalBtn').addEventListener('click', function() {
    const reason = document.getElementById('disposalReasonText').value.trim();
    if (!reason) {
      alert('Please enter a reason for disposal');
      return;
    }

    // Store data
    localStorage.setItem('selectedItemsForDisposal', JSON.stringify(selectedItems));
    localStorage.setItem('disposalReason', reason);

    // Remove modal
    modal.remove();

    // Open condemnation form
    window.location.href = 'condemnationForm.html';
  });

  document.getElementById('cancelDisposalBtn').addEventListener('click', function() {
    modal.remove();
  });
}

function openModal(row, isAlert = false) {
  document.getElementById("editSrNo").value = row.sr_no;
  document.getElementById("editItemId").value = row.name_of_the_item;
  document.getElementById("editLastMaintenance").value = row.last_maintenance || "";
  document.getElementById("editMaintenanceDue").value = row.maintenance_due || "";
  document.getElementById("editServiceProvider").value = row.service_provider || "";
  document.getElementById("editModal").dataset.alert = isAlert;
  document.getElementById("editModal").style.display = "block";

  // Auto-calculate maintenance due date when last maintenance changes
  document.getElementById("editLastMaintenance").addEventListener("change", function() {
    if (this.value) {
      const dueDate = new Date(this.value);
      dueDate.setFullYear(dueDate.getFullYear() + 10);
      document.getElementById("editMaintenanceDue").value = dueDate.toISOString().split('T')[0];
    }
  });
}

function closeModal() {
  document.getElementById("editModal").style.display = "none";
}

// Edit form submission
document.getElementById("editForm").addEventListener("submit", function(e) {
  e.preventDefault();

  const formData = new FormData(this);
  formData.append("is_alert", document.getElementById("editModal").dataset.alert === "true");

  fetch("update_maintenance.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === "success") {
      closeModal();
      alert("Maintenance updated successfully");
      fetchMaintenanceData();
      if (document.getElementById("alertsTable").style.display === "table") {
        loadAlerts();
      }
    } else {
      throw new Error(data.message || "Update failed");
    }
  })
  .catch(error => {
    console.error("Error:", error);
    alert("Error updating maintenance: " + error.message);
  });
});
