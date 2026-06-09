// ── Sidebar Toggle for Mobile ──

const sidebar = document.querySelector(".sidebar"); // Reference to the sidebar element
const menuToggle = document.querySelector(".menu-toggle"); // The hamburger button

// Create a dark overlay element that appears behind the sidebar when it's open on mobile
const overlay = document.createElement("div");
overlay.className = "sidebar-overlay";
overlay.style.cssText =
  "display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:199";
  // inset:0 means it covers the entire viewport; z-index keeps it above content but below the sidebar
document.body.appendChild(overlay); // Add the overlay to the DOM

// When the hamburger button is clicked, toggle the sidebar open/closed
if (menuToggle) {
  menuToggle.addEventListener("click", () => {
    sidebar.classList.toggle("open"); // Add or remove the 'open' class
    // Show overlay when sidebar is open; hide it when closed
    overlay.style.display = sidebar.classList.contains("open")
      ? "block"
      : "none";
  });
}

// When the overlay is clicked, close the sidebar and hide the overlay
overlay.addEventListener("click", () => {
  sidebar.classList.remove("open");
  overlay.style.display = "none";
});

// ── Modal Helpers ──

// openModal: shows the modal with the given id by adding the 'open' CSS class
function openModal(id) {
  document.getElementById(id).classList.add("open");
}

// closeModal: hides the modal with the given id by removing the 'open' CSS class
function closeModal(id) {
  document.getElementById(id).classList.remove("open");
}

// Close any modal when the user clicks outside it (on the dark overlay background)
document.querySelectorAll(".modal-overlay").forEach((el) => {
  el.addEventListener("click", (e) => {
    if (e.target === el) el.classList.remove("open"); // Only close if the click was directly on the overlay
  });
});

// ── Attendance Toggle Buttons ──
// These are the Present/Absent toggle buttons on the Take Attendance page.
// FIX: use data-adm + getElementById so we always hit the statuses[] input,
// not the first hidden input in the row (which is admissionNos[])
document.querySelectorAll(".toggle-btn").forEach((btn) => {
  btn.addEventListener("click", () => {
    const adm = btn.dataset.adm; // Get the student's admission number from data-adm attribute
    const input = document.getElementById("status-" + adm); // Find the matching hidden input for this student
    if (btn.classList.contains("absent")) {
      // Currently "Absent" → switch to "Present"
      btn.classList.replace("absent", "present"); // Update button style
      btn.textContent = "Present"; // Update button text
      if (input) input.value = "1"; // Set hidden input value to 1 (present)
    } else {
      // Currently "Present" → switch to "Absent"
      btn.classList.replace("present", "absent"); // Update button style
      btn.textContent = "Absent"; // Update button text
      if (input) input.value = "0"; // Set hidden input value to 0 (absent)
    }
  });
});

// ── Auto-Dismiss Alerts ──
// Any alert with the data-auto-dismiss attribute fades out after 3 seconds
document.querySelectorAll(".alert[data-auto-dismiss]").forEach((el) => {
  setTimeout(() => (el.style.opacity = "0"), 3000); // Start fade-out at 3 seconds
  setTimeout(() => el.remove(), 3500); // Remove from DOM 500ms after fade completes
});