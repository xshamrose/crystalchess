/**
 * Main JavaScript File
 * Crystal Chess Tournament Booking Platform
 */

// Initialize on DOM load
document.addEventListener("DOMContentLoaded", function () {
  initializeApp();
});

/**
 * Initialize Application
 */
function initializeApp() {
  // Auto-hide flash messages after 5 seconds
  setTimeout(() => {
    const flashMessages = document.querySelectorAll('[x-data*="show"]');
    flashMessages.forEach((msg) => {
      if (msg.__x) {
        msg.__x.$data.show = false;
      }
    });
  }, 5000);

  // Initialize tooltips
  initializeTooltips();

  // Initialize form validations
  initializeFormValidations();

  // Initialize image previews
  initializeImagePreviews();
}

/**
 * Show Loading Spinner
 */
function showLoading() {
  const overlay = document.createElement("div");
  overlay.className = "loading-overlay";
  overlay.id = "loading-overlay";
  overlay.innerHTML = '<div class="spinner"></div>';
  document.body.appendChild(overlay);
}

/**
 * Hide Loading Spinner
 */
function hideLoading() {
  const overlay = document.getElementById("loading-overlay");
  if (overlay) {
    overlay.remove();
  }
}

/**
 * Show Notification
 */
function showNotification(message, type = "info") {
  const colors = {
    success: "bg-green-100 border-green-500 text-green-800",
    error: "bg-red-100 border-red-500 text-red-800",
    warning: "bg-yellow-100 border-yellow-500 text-yellow-800",
    info: "bg-blue-100 border-blue-500 text-blue-800",
  };

  const icons = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    warning: "fa-exclamation-triangle",
    info: "fa-info-circle",
  };

  const notification = document.createElement("div");
  notification.className = `fixed top-4 right-4 z-50 max-w-md ${colors[type]} border-l-4 p-4 rounded-lg shadow-lg`;
  notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${icons[type]} mr-3"></i>
                <p>${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-xl">&times;</button>
        </div>
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.remove();
  }, 5000);
}

/**
 * Confirm Dialog
 */
function confirmAction(message, callback) {
  if (confirm(message)) {
    callback();
  }
}

/**
 * Format Currency
 */
function formatCurrency(amount) {
  return "$" + parseFloat(amount).toFixed(2);
}

/**
 * Format Date
 */
function formatDate(dateString) {
  const date = new Date(dateString);
  const options = { year: "numeric", month: "short", day: "numeric" };
  return date.toLocaleDateString("en-US", options);
}

/**
 * Debounce Function
 */
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Initialize Tooltips
 */
function initializeTooltips() {
  const tooltips = document.querySelectorAll("[data-tooltip]");
  tooltips.forEach((element) => {
    const text = element.getAttribute("data-tooltip");
    element.classList.add("tooltip");

    const tooltipText = document.createElement("span");
    tooltipText.className = "tooltiptext";
    tooltipText.textContent = text;
    element.appendChild(tooltipText);
  });
}

/**
 * Initialize Form Validations
 */
function initializeFormValidations() {
  const forms = document.querySelectorAll("form[data-validate]");

  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      let isValid = true;

      // Required fields
      const requiredFields = form.querySelectorAll("[required]");
      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          isValid = false;
          showFieldError(field, "This field is required");
        } else {
          clearFieldError(field);
        }
      });

      // Email fields
      const emailFields = form.querySelectorAll('input[type="email"]');
      emailFields.forEach((field) => {
        if (field.value && !isValidEmail(field.value)) {
          isValid = false;
          showFieldError(field, "Please enter a valid email address");
        }
      });

      if (!isValid) {
        e.preventDefault();
      }
    });

    // Real-time validation
    const inputs = form.querySelectorAll("input, textarea, select");
    inputs.forEach((input) => {
      input.addEventListener("blur", function () {
        if (this.hasAttribute("required") && !this.value.trim()) {
          showFieldError(this, "This field is required");
        } else if (
          this.type === "email" &&
          this.value &&
          !isValidEmail(this.value)
        ) {
          showFieldError(this, "Please enter a valid email address");
        } else {
          clearFieldError(this);
        }
      });
    });
  });
}

/**
 * Show Field Error
 */
function showFieldError(field, message) {
  field.classList.add("input-error");

  let errorDiv = field.parentElement.querySelector(".error-message");
  if (!errorDiv) {
    errorDiv = document.createElement("div");
    errorDiv.className = "error-message";
    field.parentElement.appendChild(errorDiv);
  }
  errorDiv.textContent = message;
}

/**
 * Clear Field Error
 */
function clearFieldError(field) {
  field.classList.remove("input-error");
  const errorDiv = field.parentElement.querySelector(".error-message");
  if (errorDiv) {
    errorDiv.remove();
  }
}

/**
 * Validate Email
 */
function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

/**
 * Initialize Image Previews
 */
function initializeImagePreviews() {
  const imageInputs = document.querySelectorAll(
    'input[type="file"][accept*="image"]'
  );

  imageInputs.forEach((input) => {
    input.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          let preview = input.parentElement.querySelector(".image-preview");
          if (!preview) {
            preview = document.createElement("img");
            preview.className =
              "image-preview mt-2 w-32 h-32 object-cover rounded-lg border-2 border-gray-300";
            input.parentElement.appendChild(preview);
          }
          preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  });
}

/**
 * AJAX Helper Function
 */
async function ajaxRequest(url, method = "GET", data = null) {
  showLoading();

  try {
    const options = {
      method: method,
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    };

    if (data && method !== "GET") {
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    const result = await response.json();

    hideLoading();
    return result;
  } catch (error) {
    hideLoading();
    showNotification("An error occurred. Please try again.", "error");
    console.error("AJAX Error:", error);
    return null;
  }
}

/**
 * Copy to Clipboard
 */
function copyToClipboard(text) {
  navigator.clipboard
    .writeText(text)
    .then(() => {
      showNotification("Copied to clipboard!", "success");
    })
    .catch(() => {
      showNotification("Failed to copy", "error");
    });
}

/**
 * Scroll to Top
 */
function scrollToTop() {
  window.scrollTo({ top: 0, behavior: "smooth" });
}

// Add scroll to top button
window.addEventListener("scroll", function () {
  const scrollBtn = document.getElementById("scroll-top-btn");
  if (scrollBtn) {
    if (window.pageYOffset > 300) {
      scrollBtn.style.display = "block";
    } else {
      scrollBtn.style.display = "none";
    }
  }
});

/**
 * Print Function
 */
function printPage() {
  window.print();
}

/**
 * Export to CSV (for tables)
 */
function exportTableToCSV(tableId, filename = "data.csv") {
  const table = document.getElementById(tableId);
  if (!table) return;

  let csv = [];
  const rows = table.querySelectorAll("tr");

  rows.forEach((row) => {
    const cols = row.querySelectorAll("td, th");
    const csvRow = [];
    cols.forEach((col) => {
      csvRow.push(col.textContent.trim());
    });
    csv.push(csvRow.join(","));
  });

  const csvContent = csv.join("\n");
  const blob = new Blob([csvContent], { type: "text/csv" });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  window.URL.revokeObjectURL(url);
}

// Expose functions globally
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.showNotification = showNotification;
window.confirmAction = confirmAction;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.ajaxRequest = ajaxRequest;
window.copyToClipboard = copyToClipboard;
window.scrollToTop = scrollToTop;
window.printPage = printPage;
window.exportTableToCSV = exportTableToCSV;
