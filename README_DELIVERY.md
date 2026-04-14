# 🎓 Intern Management System (IMSJR) - Delivery Package

A comprehensive web-based platform designed for managing interns, tracking tasks, and evaluating performance.

## 🚀 Getting Started

To get this project up and running on your local machine (using XAMPP):

### 1. Project Setup
1.  **Extract the files:** Place the `imsjr` folder into your XAMPP's `htdocs` directory (e.g., `C:\xampp\htdocs\imsjr`).
2.  **Ensure MySQL & Apache are running:** Open the XAMPP Control Panel and start both modules.

### 2. Database Import
1.  **Open phpMyAdmin:** Go to `http://localhost/phpmyadmin` in your browser.
2.  **Create/Import the Database:**
    *   Click on the **Import** tab at the top.
    *   Click "Choose File" and select the `ims_sample_export.sql` file located inside the project folder.
    *   Scroll down and click **Go**.
    *   *Note: The script will automatically create the `ims_sample` database for you.*

### 3. Application Access
1.  **Open the App:** Navigate to `http://localhost/imsjr` in your browser.
2.  **Login Credentials:**
    *   **Admin:** `admin` / `admin123` (or the credentials you previously setup)
    *   **Team Lead & Intern:** Use the credentials created through the Admin panel.

---

## 🛠 Features Summary (Recently Updated)

-   **Admin Dashboard:** Full performance analytics including **Star Ratings** for each intern.
-   **Team Lead Module:**
    *   Quick task assignment (only **Task Title** is required for speed).
    *   Flexible deadlines and optional task descriptions.
    *   Ability to rate tasks from 1 to 5 stars upon completion.
-   **Intern Module:**
    *   Task submission with **Mandatory Submission Notes**.
    *   **Optional File Upload** support for flexible workflows.
    *   Visual progress tracking on the dashboard.

## 📁 Key File Structure
-   `/admin`: Management of users, domains, and performance.
-   `/teamlead`: Task assignment and evaluation.
-   `/intern`: Task viewing and submission.
-   `/config`: Central database and performance helper logic.

---

*Delivered with care. If you have any further questions, please refer to the documentation or the codebase.*
