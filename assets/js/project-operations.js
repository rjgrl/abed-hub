/**
 * Project Operations Module - Handles all project-related AJAX operations
 */

class ProjectManager {
  constructor(projectType = "fspf") {
    this.projectType = projectType;
    this.baseUrl = "/api/projects.php";
    this.eventsEnabled = true;
  }

  /**
   * Get all projects with optional filtering
   */
  async getProjects(options = {}) {
    const params = new URLSearchParams({
      action: "list",
      type: this.projectType,
      limit: options.limit || 10,
      offset: options.offset || 0,
      search: options.search || "",
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`);
      if (!response.ok) throw new Error("Failed to fetch projects");
      return await response.json();
    } catch (error) {
      console.error("Error fetching projects:", error);
      throw error;
    }
  }

  /**
   * Get single project details
   */
  async getProject(projectId) {
    const params = new URLSearchParams({
      action: "get",
      type: this.projectType,
      id: projectId,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`);
      if (!response.ok) throw new Error("Failed to fetch project");
      return await response.json();
    } catch (error) {
      console.error("Error fetching project:", error);
      throw error;
    }
  }

  /**
   * Update project details
   */
  async updateProject(projectId, data) {
    const params = new URLSearchParams({
      action: "update",
      type: this.projectType,
      id: projectId,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
        method: "POST",
        body: new URLSearchParams(data),
      });

      if (!response.ok) throw new Error("Failed to update project");
      const result = await response.json();

      if (this.eventsEnabled) {
        this.dispatchEvent("projectUpdated", { projectId, data });
      }

      return result;
    } catch (error) {
      console.error("Error updating project:", error);
      throw error;
    }
  }

  /**
   * Get project statistics
   */
  async getProjectStats() {
    const params = new URLSearchParams({
      action: "stats",
      type: this.projectType,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`);
      if (!response.ok) throw new Error("Failed to fetch stats");
      return await response.json();
    } catch (error) {
      console.error("Error fetching stats:", error);
      throw error;
    }
  }

  /**
   * Approve project
   */
  async approveProject(projectId) {
    const params = new URLSearchParams({
      action: "approve",
      type: this.projectType,
      id: projectId,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
        method: "POST",
      });

      if (!response.ok) throw new Error("Failed to approve project");
      const result = await response.json();

      if (this.eventsEnabled) {
        this.dispatchEvent("projectApproved", { projectId });
      }

      return result;
    } catch (error) {
      console.error("Error approving project:", error);
      throw error;
    }
  }

  /**
   * Reject project
   */
  async rejectProject(projectId, reason) {
    const params = new URLSearchParams({
      action: "reject",
      type: this.projectType,
      id: projectId,
    });

    const formData = new URLSearchParams({
      reason: reason,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Failed to reject project");
      const result = await response.json();

      if (this.eventsEnabled) {
        this.dispatchEvent("projectRejected", { projectId, reason });
      }

      return result;
    } catch (error) {
      console.error("Error rejecting project:", error);
      throw error;
    }
  }

  /**
   * Delete project
   */
  async deleteProject(projectId) {
    const params = new URLSearchParams({
      action: "delete",
      type: this.projectType,
      id: projectId,
    });

    const ok = await AppModal.confirm("Are you sure you want to delete this project?", {
      title: "Delete project",
      variant: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) {
      return { cancelled: true };
    }

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
        method: "POST",
      });

      if (!response.ok) throw new Error("Failed to delete project");
      const result = await response.json();

      if (this.eventsEnabled) {
        this.dispatchEvent("projectDeleted", { projectId });
      }

      return result;
    } catch (error) {
      console.error("Error deleting project:", error);
      throw error;
    }
  }

  /**
   * Update project progress
   */
  async updateProgress(projectId, physicalProgress, financialProgress) {
    return this.updateProject(projectId, {
      physical_progress: physicalProgress,
      financial_progress: financialProgress,
    });
  }

  /**
   * Update project stage
   */
  async updateStage(projectId, stage) {
    return this.updateProject(projectId, {
      current_stage: stage,
    });
  }

  /**
   * Export projects to CSV
   */
  exportToCSV(projects, filename = "projects.csv") {
    if (!Array.isArray(projects) || projects.length === 0) {
      console.error("No projects to export");
      return;
    }

    const headers = Object.keys(projects[0]);
    const rows = [headers, ...projects.map((p) => headers.map((h) => p[h]))];

    const csv = rows
      .map((row) =>
        row
          .map((cell) =>
            typeof cell === "string" && cell.includes(",") ? `"${cell}"` : cell,
          )
          .join(","),
      )
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
  }

  /**
   * Dispatch custom event
   */
  dispatchEvent(eventName, detail) {
    const event = new CustomEvent(eventName, { detail });
    document.dispatchEvent(event);
  }

  /**
   * Listen to project events
   */
  addEventListener(eventName, callback) {
    document.addEventListener(eventName, (e) => callback(e.detail));
  }

  /**
   * Format project data for display
   */
  formatProject(project) {
    return {
      ...project,
      formattedProposedAmount: this.formatCurrency(project.proposed_amount),
      formattedAllocatedAmount: this.formatCurrency(project.allocated_amount),
      formattedCreatedDate: this.formatDate(project.created_date),
      formattedUpdatedDate: this.formatDate(project.updated_at),
      progressVariance: (
        project.physical_progress - project.financial_progress
      ).toFixed(1),
    };
  }

  /**
   * Format currency
   */
  formatCurrency(value) {
    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: "PHP",
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value || 0);
  }

  /**
   * Format date
   */
  formatDate(dateString) {
    return new Intl.DateTimeFormat("en-PH", {
      year: "numeric",
      month: "long",
      day: "numeric",
    }).format(new Date(dateString));
  }

  /**
   * Get stage color
   */
  getStageColor(stage) {
    const colors = {
      Proposal: "#9ca3af",
      "Pre-Implementation": "#7eb8d9",
      Procurement: "#f5c57a",
      Implementation: "#5b8def",
      Completed: "#5fd4a8",
      "Turned-Over": "#5fd4a8",
    };
    return colors[stage] || "#e3e3e3";
  }

  /**
   * Get type color
   */
  getTypeColor(type) {
    const colors = {
      fspf: "#5b8def",
      idp: "#c694f9",
      afme: "#f5c57a",
    };
    return colors[type.toLowerCase()] || "#9ca3af";
  }

  /**
   * Parse project filters from URL
   */
  getFiltersFromURL() {
    const params = new URLSearchParams(window.location.search);
    return {
      stage: params.get("stage") || null,
      search: params.get("search") || null,
      year: params.get("year") || new Date().getFullYear(),
      status: params.get("status") || null,
    };
  }

  /**
   * Build filter URL
   */
  buildFilterURL(filters) {
    const params = new URLSearchParams({
      type: this.projectType,
      ...filters,
    });
    return `?${params.toString()}`;
  }
}

/**
 * Milestone Manager
 */
class MilestoneManager {
  constructor() {
    this.baseUrl = "/api/milestones.php";
  }

  /**
   * Create milestone
   */
  async createMilestone(data) {
    const formData = new URLSearchParams(data);

    try {
      const response = await fetch(`${this.baseUrl}?action=create`, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Failed to create milestone");
      return await response.json();
    } catch (error) {
      console.error("Error creating milestone:", error);
      throw error;
    }
  }

  /**
   * Get milestones
   */
  async getMilestones(projectId, projectType) {
    const params = new URLSearchParams({
      action: "list",
      project_id: projectId,
      project_type: projectType,
    });

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`);
      if (!response.ok) throw new Error("Failed to fetch milestones");
      return await response.json();
    } catch (error) {
      console.error("Error fetching milestones:", error);
      throw error;
    }
  }

  /**
   * Update milestone
   */
  async updateMilestone(milestoneId, data) {
    const params = new URLSearchParams({
      action: "update",
      id: milestoneId,
    });

    const formData = new URLSearchParams(data);

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("Failed to update milestone");
      return await response.json();
    } catch (error) {
      console.error("Error updating milestone:", error);
      throw error;
    }
  }

  /**
   * Delete milestone
   */
  async deleteMilestone(milestoneId) {
    const params = new URLSearchParams({
      action: "delete",
      id: milestoneId,
    });

    const ok = await AppModal.confirm("Delete this milestone?", {
      title: "Delete milestone",
      variant: "danger",
      confirmLabel: "Delete",
    });
    if (!ok) {
      return { cancelled: true };
    }

    try {
      const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
        method: "POST",
      });

      if (!response.ok) throw new Error("Failed to delete milestone");
      return await response.json();
    } catch (error) {
      console.error("Error deleting milestone:", error);
      throw error;
    }
  }

  /**
   * Get progress status color
   */
  getStatusColor(status) {
    const colors = {
      "On Track": "#5fd4a8",
      "At Risk": "#f5c57a",
      Delayed: "#dc3545",
      Completed: "#7eb8d9",
    };
    return colors[status] || "#9ca3af";
  }
}

// Global instances
const projectManager = new ProjectManager();
const milestoneManager = new MilestoneManager();
