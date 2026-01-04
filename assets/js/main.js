/**
 * Thread Extruder Monitoring System - Main JavaScript
 */

// Utility Functions
const Utils = {
  formatNumber: function (num, decimals = 0) {
    return new Intl.NumberFormat("id-ID", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    }).format(num);
  },

  formatDateTime: function (date) {
    return new Intl.DateTimeFormat("id-ID", {
      dateStyle: "medium",
      timeStyle: "medium",
    }).format(new Date(date));
  },

  formatTime: function (date) {
    return new Intl.DateTimeFormat("id-ID", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    }).format(new Date(date));
  },

  formatDate: function (date) {
    return new Intl.DateTimeFormat("id-ID", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
    }).format(new Date(date));
  },

  calculatePercentage: function (part, total) {
    if (total === 0) return 0;
    return Math.round((part / total) * 100 * 10) / 10;
  },

  getShift: function () {
    const hour = new Date().getHours();
    if (hour >= 6 && hour < 14) return "A";
    if (hour >= 14 && hour < 22) return "B";
    return "C";
  },

  debounce: function (func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  showLoading: function (element) {
    element.innerHTML = `
            <div class="text-center py-5">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Loading...</p>
            </div>
        `;
  },

  showEmptyState: function (
    element,
    message = "No data available",
    icon = "fa-inbox"
  ) {
    element.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <h4 class="empty-state-title">${message}</h4>
            </div>
        `;
  },
};

// Notification System
class Notification {
  static show(message, type = "info", duration = 3000) {
    // Create notification container if it doesn't exist
    let container = document.getElementById("notification-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "notification-container";
      container.className = "position-fixed top-0 end-0 p-3";
      container.style.zIndex = "1060";
      document.body.appendChild(container);
    }

    // Create notification element
    const notification = document.createElement("div");
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.role = "alert";
    notification.innerHTML = `
            <i class="fas ${this.getIcon(type)} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

    // Add to container
    container.appendChild(notification);

    // Auto remove after duration
    if (duration > 0) {
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, duration);
    }

    // Return notification element for manual control
    return notification;
  }

  static getIcon(type) {
    const icons = {
      success: "fa-check-circle",
      danger: "fa-exclamation-circle",
      warning: "fa-exclamation-triangle",
      info: "fa-info-circle",
    };
    return icons[type] || "fa-info-circle";
  }
}

// API Service
class APIService {
  static baseURL = (window.APP_BASE_PATH || "") + "api/";

  static async get(endpoint, params = {}) {
    try {
      const url = new URL(this.baseURL + endpoint, window.location.origin);
      Object.keys(params).forEach((key) =>
        url.searchParams.append(key, params[key])
      );

      const response = await fetch(url);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return await response.json();
    } catch (error) {
      console.error("API Error:", error);
      Notification.show("Connection error. Please check network.", "danger");
      throw error;
    }
  }

  static async post(endpoint, data = {}) {
    try {
      const response = await fetch(this.baseURL + endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return await response.json();
    } catch (error) {
      console.error("API Error:", error);
      Notification.show("Connection error. Please check network.", "danger");
      throw error;
    }
  }

  // Machine Control Methods
  static async controlMachine(status) {
    return this.get("status.php", { mode: "control_update", status });
  }

  static async getMachineStatus() {
    return this.get("status.php", { mode: "control_baca" });
  }

  static async recordProduction(status, duration, qty) {
    return this.get("status.php", {
      mode: "input_status",
      status,
      duration,
      qty,
    });
  }

  static async recordQualityCheck(result, qty, defect = "NONE") {
    return this.get("status.php", {
      mode: "input_tread_checked",
      result,
      qty,
      defect,
    });
  }

  // Data Fetching Methods
  static async getProductionData(hours = 24) {
    return this.get("production.php", { hours });
  }

  static async getQualityData(hours = 24) {
    return this.get("quality.php", { hours });
  }

  static async getOEEData(hours = 8, idealRate = 60) {
    return this.get("oee.php", {
      hours,
      ideal_rate_per_hour: idealRate,
    });
  }

  static async getDashboardData() {
    return this.get("status.php");
  }
}

// Chart Manager
class ChartManager {
  static charts = new Map();

  static create(id, type, data, options = {}) {
    const ctx = document.getElementById(id).getContext("2d");

    // Default options
    const defaultOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "top",
        },
        tooltip: {
          mode: "index",
          intersect: false,
        },
      },
    };

    // Merge options
    const mergedOptions = this.mergeDeep(defaultOptions, options);

    // Create chart
    const chart = new Chart(ctx, {
      type: type,
      data: data,
      options: mergedOptions,
    });

    // Store reference
    this.charts.set(id, chart);

    return chart;
  }

  static update(id, newData) {
    const chart = this.charts.get(id);
    if (chart) {
      chart.data = newData;
      chart.update();
    }
  }

  static destroy(id) {
    const chart = this.charts.get(id);
    if (chart) {
      chart.destroy();
      this.charts.delete(id);
    }
  }

  static getChart(id) {
    return this.charts.get(id);
  }

  // Deep merge helper function
  static mergeDeep(target, source) {
    const output = Object.assign({}, target);
    if (this.isObject(target) && this.isObject(source)) {
      Object.keys(source).forEach((key) => {
        if (this.isObject(source[key])) {
          if (!(key in target)) Object.assign(output, { [key]: source[key] });
          else output[key] = this.mergeDeep(target[key], source[key]);
        } else {
          Object.assign(output, { [key]: source[key] });
        }
      });
    }
    return output;
  }

  static isObject(item) {
    return item && typeof item === "object" && !Array.isArray(item);
  }

  // Common chart configurations
  static getLineChartConfig(label, data, color = "#3498db") {
    return {
      labels: data.labels || [],
      datasets: [
        {
          label: label,
          data: data.values || [],
          borderColor: color,
          backgroundColor: this.hexToRgba(color, 0.1),
          borderWidth: 2,
          fill: true,
          tension: 0.4,
        },
      ],
    };
  }

  static getBarChartConfig(label, data, color = "#3498db") {
    return {
      labels: data.labels || [],
      datasets: [
        {
          label: label,
          data: data.values || [],
          backgroundColor: color,
          borderColor: this.adjustColor(color, -20),
          borderWidth: 1,
        },
      ],
    };
  }

  static getDoughnutChartConfig(
    labels,
    data,
    colors = ["#3498db", "#2ecc71", "#e74c3c"]
  ) {
    return {
      labels: labels,
      datasets: [
        {
          data: data,
          backgroundColor: colors,
          borderWidth: 2,
          borderColor: "#ffffff",
        },
      ],
    };
  }

  // Utility color functions
  static hexToRgba(hex, alpha = 1) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  static adjustColor(color, amount) {
    return (
      "#" +
      color
        .replace(/^#/, "")
        .replace(/../g, (color) =>
          (
            "0" +
            Math.min(255, Math.max(0, parseInt(color, 16) + amount)).toString(
              16
            )
          ).substr(-2)
        )
    );
  }
}

// Dashboard Manager
class DashboardManager {
  static async updateAll() {
    try {
      // Update machine status
      await this.updateMachineStatus();

      // Update production data
      await this.updateProductionData();

      // Update OEE data
      await this.updateOEEData();

      Notification.show("Dashboard updated successfully!", "success", 2000);
    } catch (error) {
      console.error("Dashboard update error:", error);
    }
  }

  static async updateMachineStatus() {
    try {
      const data = await APIService.getMachineStatus();
      if (data.ok) {
        this.updateMachineStatusUI(data);
      }
    } catch (error) {
      console.error("Failed to update machine status:", error);
    }
  }

  static updateMachineStatusUI(data) {
    // Update status indicator
    const indicator = document.querySelector("#machineStatusIndicator");
    const statusText = document.querySelector("#machineStatusText");
    const onButton = document.querySelector("#btnMachineOn");
    const offButton = document.querySelector("#btnMachineOff");

    if (indicator && statusText) {
      if (data.control_status === "ON") {
        indicator.className = "status-indicator status-running";
        statusText.textContent = "RUNNING";
        if (onButton) onButton.disabled = true;
        if (offButton) offButton.disabled = false;
      } else {
        indicator.className = "status-indicator status-stopped";
        statusText.textContent = "STOPPED";
        if (onButton) onButton.disabled = false;
        if (offButton) offButton.disabled = true;
      }
    }
  }

  static async updateProductionData() {
    try {
      const data = await APIService.getProductionData(24);
      if (data.ok) {
        this.updateProductionUI(data);
      }
    } catch (error) {
      console.error("Failed to update production data:", error);
    }
  }

  static updateProductionUI(data) {
    // Update production counters
    const elements = {
      totalProduction: data.total_qty || 0,
      todayProduction: data.total_qty || 0,
      okProduction: data.ok_qty || 0,
      ngProduction: data.ng_qty || 0,
    };

    Object.keys(elements).forEach((id) => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = Utils.formatNumber(elements[id]);
      }
    });
  }

  static async updateOEEData() {
    try {
      const data = await APIService.getOEEData(8, 60);
      if (data.ok) {
        this.updateOEEUI(data);
      }
    } catch (error) {
      console.error("Failed to update OEE data:", error);
    }
  }

  static updateOEEUI(data) {
    // Update OEE scores
    const elements = {
      oeeScore: data.oee || 0,
      availabilityScore: data.availability?.A || 0,
      performanceScore: data.performance?.P || 0,
      qualityScore: data.quality?.Q || 0,
    };

    Object.keys(elements).forEach((id) => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = Utils.formatNumber(elements[id], 1) + "%";
      }
    });

    // Update runtime and downtime
    const runtimeElement = document.getElementById("runtimeTotal");
    const downtimeElement = document.getElementById("downtimeTotal");

    if (runtimeElement && data.availability) {
      runtimeElement.textContent =
        Utils.formatNumber(data.availability.runtime_seconds / 3600, 1) +
        " hours";
    }

    if (downtimeElement && data.availability) {
      downtimeElement.textContent =
        Utils.formatNumber(data.availability.downtime_seconds / 3600, 1) +
        " hours";
    }
  }

  static initializeAutoRefresh(interval = 30000) {
    // Auto-refresh dashboard every 30 seconds
    setInterval(() => {
      this.updateAll();
    }, interval);

    // Also update on page visibility change
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) {
        this.updateAll();
      }
    });
  }
}

// Machine Control Handler
class MachineControl {
  static async turnOn(duration = 300, qty = 1) {
    try {
      Notification.show("Turning machine ON...", "info");
      const result = await APIService.recordProduction("ON", duration, qty);

      if (result.ok) {
        Notification.show("Machine turned ON successfully!", "success");
        DashboardManager.updateAll();
        return true;
      } else {
        Notification.show(
          "Failed to turn machine ON: " + result.error,
          "danger"
        );
        return false;
      }
    } catch (error) {
      Notification.show("Error turning machine ON", "danger");
      return false;
    }
  }

  static async turnOff(duration = 600) {
    try {
      Notification.show("Turning machine OFF...", "info");
      const result = await APIService.recordProduction("OFF", duration);

      if (result.ok) {
        Notification.show("Machine turned OFF successfully!", "success");
        DashboardManager.updateAll();
        return true;
      } else {
        Notification.show(
          "Failed to turn machine OFF: " + result.error,
          "danger"
        );
        return false;
      }
    } catch (error) {
      Notification.show("Error turning machine OFF", "danger");
      return false;
    }
  }

  static async recordQuality(result, qty = 1, defect = "NONE") {
    try {
      Notification.show("Recording quality check...", "info");
      const data = await APIService.recordQualityCheck(result, qty, defect);

      if (data.ok) {
        const message =
          result === "OK" ? "Quality OK recorded!" : "Quality NG recorded!";
        Notification.show(message, "success");
        DashboardManager.updateAll();
        return true;
      } else {
        Notification.show("Failed to record quality: " + data.error, "danger");
        return false;
      }
    } catch (error) {
      Notification.show("Error recording quality check", "danger");
      return false;
    }
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Initialize popovers
  const popoverTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="popover"]')
  );
  popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
  });

  // Sidebar toggle for mobile
  const sidebarToggle = document.getElementById("sidebarToggle");
  const sidebar = document.getElementById("sidebar");
  const contentWrapper = document.getElementById("contentWrapper");

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener("click", function () {
      sidebar.classList.toggle("active");
      contentWrapper.classList.toggle("active");
    });
  }

  // Close sidebar when clicking outside on mobile
  document.addEventListener("click", function (event) {
    if (
      window.innerWidth <= 768 &&
      sidebar &&
      sidebar.classList.contains("active")
    ) {
      if (
        !sidebar.contains(event.target) &&
        !sidebarToggle.contains(event.target)
      ) {
        sidebar.classList.remove("active");
        contentWrapper.classList.remove("active");
      }
    }
  });

  // Initialize dashboard auto-refresh
  if (window.IS_DASHBOARD) {
    DashboardManager.initializeAutoRefresh();
    DashboardManager.updateAll();
  }

  // Export globals
  window.Utils = Utils;
  window.Notification = Notification;
  window.APIService = APIService;
  window.ChartManager = ChartManager;
  window.DashboardManager = DashboardManager;
  window.MachineControl = MachineControl;

  console.log("Thread Extruder Monitoring System initialized");
});
