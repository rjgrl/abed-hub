/**
 * Notifications Manager - Real-time notification handling
 */

class NotificationManager {
  constructor() {
    this.baseUrl = "/api/notifications.php";
    this.pollInterval = 30000; // 30 seconds
    this.isPolling = false;
    this.notificationBox = null;
    this.unreadCount = 0;
  }

  /**
   * Initialize notification system
   */
  async init() {
    // Create notification box if doesn't exist
    if (!document.getElementById("notificationBox")) {
      this.createNotificationBox();
    }

    this.notificationBox = document.getElementById("notificationBox");

    // Start polling for notifications
    this.startPolling();

    // Listen for visibility changes to pause/resume polling
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        this.stopPolling();
      } else {
        this.startPolling();
      }
    });
  }

  /**
   * Create notification box HTML
   */
  createNotificationBox() {
    const html = `
            <div id="notificationBox" style="position: fixed; top: 80px; right: 20px; z-index: 1050; width: 400px; max-height: 600px; display: none; overflow-y: auto;">
                <div id="notificationContainer"></div>
            </div>
        `;
    document.body.insertAdjacentHTML("beforeend", html);
  }

  /**
   * Get unread notifications count
   */
  async getUnreadCount() {
    try {
      const response = await fetch(`${this.baseUrl}?action=count`);
      if (!response.ok) throw new Error("Failed to fetch count");

      const result = await response.json();
      this.unreadCount = result.unread_count;
      this.updateBadge();
      return result.unread_count;
    } catch (error) {
      console.error("Error getting unread count:", error);
      return 0;
    }
  }

  /**
   * Get all notifications
   */
  async getNotifications(unreadOnly = false) {
    const params = new URLSearchParams({
      action: "get",
      unread: unreadOnly ? "1" : "0",
      limit: 20,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`);
      if (!response.ok) throw new Error("Failed to fetch notifications");

      return await response.json();
    } catch (error) {
      console.error("Error fetching notifications:", error);
      return { success: false, data: [] };
    }
  }

  /**
   * Mark notification as read
   */
  async markAsRead(notificationId) {
    const formData = new URLSearchParams({
      action: "mark_read",
      notification_id: notificationId,
    });

    try {
      const response = await fetch(this.baseUrl, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Failed to mark as read");
      return await response.json();
    } catch (error) {
      console.error("Error marking as read:", error);
      return { success: false };
    }
  }

  /**
   * Mark all as read
   */
  async markAllAsRead() {
    const formData = new URLSearchParams({
      action: "mark_all_read",
    });

    try {
      const response = await fetch(this.baseUrl, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Failed to mark all as read");
      return await response.json();
    } catch (error) {
      console.error("Error marking all as read:", error);
      return { success: false };
    }
  }

  /**
   * Delete notification
   */
  async deleteNotification(notificationId) {
    const params = new URLSearchParams({
      action: "delete",
      id: notificationId,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
        method: "POST",
      });

      if (!response.ok) throw new Error("Failed to delete notification");
      return await response.json();
    } catch (error) {
      console.error("Error deleting notification:", error);
      return { success: false };
    }
  }

  /**
   * Show notification toast
   */
  showToast(title, message, type = "info") {
    const toastId = "toast_" + Date.now();
    const colors = {
      success: "#5fd4a8",
      error: "#dc3545",
      warning: "#ffc107",
      info: "#5b8def",
    };

    const html = `
            <div id="${toastId}" style="
                background-color: ${colors[type] || colors["info"]}; 
                color: ${type === "warning" ? "black" : "white"};
                padding: 15px;
                margin-bottom: 10px;
                border-radius: 4px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
                animation: slideIn 0.3s ease-in-out;
            ">
                <div>
                    <strong>${title}</strong><br>
                    <small>${message}</small>
                </div>
                <button onclick="document.getElementById('${toastId}').remove();" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 20px; line-height: 1;">
                    &times;
                </button>
            </div>
        `;

    if (this.notificationBox) {
      this.notificationBox.style.display = "block";
      document
        .getElementById("notificationContainer")
        .insertAdjacentHTML("beforeend", html);

      // Auto-remove after 5 seconds
      setTimeout(() => {
        const el = document.getElementById(toastId);
        if (el) el.remove();
      }, 5000);
    }
  }

  /**
   * Update notification badge
   */
  updateBadge() {
    const badges = document.querySelectorAll("[data-notification-badge]");
    badges.forEach((badge) => {
      if (this.unreadCount > 0) {
        badge.textContent = this.unreadCount;
        badge.style.display = "inline-block";
      } else {
        badge.style.display = "none";
      }
    });
  }

  /**
   * Start polling for notifications
   */
  startPolling() {
    if (this.isPolling) return;

    this.isPolling = true;
    this.pollNotifications();
  }

  /**
   * Stop polling
   */
  stopPolling() {
    this.isPolling = false;
  }

  /**
   * Poll for new notifications
   */
  async pollNotifications() {
    if (!this.isPolling) return;

    const result = await this.getUnreadCount();

    if (this.isPolling) {
      setTimeout(() => this.pollNotifications(), this.pollInterval);
    }
  }

  /**
   * Get severity color
   */
  getSeverityColor(severity) {
    const colors = {
      low: "#7eb8d9",
      medium: "#ffc107",
      high: "#fd7e14",
      critical: "#dc3545",
    };
    return colors[severity] || "#6c757d";
  }

  /**
   * Get alert icon
   */
  getAlertIcon(type) {
    const icons = {
      variance: "fa-exclamation-triangle",
      delay: "fa-clock",
      budget: "fa-money-bill",
      milestone: "fa-flag",
      approval: "fa-check-circle",
      update: "fa-refresh",
    };
    return icons[type] || "fa-bell";
  }

  /**
   * Create alert
   */
  async createAlert(projectId, alertType, severity, message, targetUsers = []) {
    const formData = new URLSearchParams({
      action: "create_alert",
      project_id: projectId,
      alert_type: alertType,
      severity: severity,
      message: message,
      target_users: JSON.stringify(targetUsers),
    });

    try {
      const response = await fetch(this.baseUrl, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Failed to create alert");
      return await response.json();
    } catch (error) {
      console.error("Error creating alert:", error);
      return { success: false };
    }
  }

  /**
   * Get project alerts
   */
  async getProjectAlerts(projectId) {
    const params = new URLSearchParams({
      action: "get_alerts",
      project_id: projectId,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`);
      if (!response.ok) throw new Error("Failed to fetch alerts");
      return await response.json();
    } catch (error) {
      console.error("Error fetching alerts:", error);
      return { success: false, data: [] };
    }
  }

  /**
   * Resolve alert
   */
  async resolveAlert(alertId) {
    const formData = new URLSearchParams({
      action: "resolve_alert",
      alert_id: alertId,
    });

    try {
      const response = await fetch(this.baseUrl, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Failed to resolve alert");
      return await response.json();
    } catch (error) {
      console.error("Error resolving alert:", error);
      return { success: false };
    }
  }
}

// Global instance
const notificationManager = new NotificationManager();

// Add CSS animation
const style = document.createElement("style");
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    #notificationBox {
        animation: slideIn 0.3s ease-in-out;
    }
`;
document.head.appendChild(style);

// Initialize on page load
document.addEventListener("DOMContentLoaded", () => {
  notificationManager.init();
});
