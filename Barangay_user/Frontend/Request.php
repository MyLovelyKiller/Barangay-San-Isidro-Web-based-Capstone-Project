<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Request Document</title>
    <link rel="stylesheet" href="../css/Request.css">
    <link rel="stylesheet" href="../css/Sidenav.css">
    <link rel="stylesheet" href="../css/Global.css">
    <link rel="stylesheet" href="../css/Footer.css">
    <script src="../js/js.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  </head>
  <body>
    <div class="container">
      <aside class="sidebar" id="sidebar">
       <div class="burgertab" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
          </div>
      <div class="sidebarcontent">
        <div class="sidebar-header">
          <div class="logosidebar">
            <img src="../picture/Logo.png" alt="Barangay Logo" id="sidebarLogo">
          </div>
        </div>

        <nav class="navlinks">
          <a href="Userdashboard.php">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
          </a>
          <a href="Profile.php">
            <i class="fa-solid fa-user"></i>
            <span>My Profile</span>
          </a>
          <a href="Request.php" class="active">
            <i class="fa-solid fa-file-circle-plus"></i>
            <span>Request a Document</span>
          </a>
          <a href="History.php">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>History</span>
          </a>
          <div class="nav-divider"></div> 
          <a href="/BMS/BACKEND/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Log out</span>
          </a>
        </nav>
      </div>
    </aside>

      <main class="maincontent">
  <div class="contentwrapper">
    <h1>Request a Document</h1>
    <p>Select a category below to start your application.</p>

    <div class="requestgrid">
      <a href="../Forms/Clearance_Permit.php" class="requestcard">
  <div class="requestboxicon">
    <img src="../picture/Clearance.png" alt="Icon" />
  </div>
  <div class="requestboxtext">
    <h3>Clearance & Permits</h3>
    <p>Barangay Clearance, Business Clearance, Barangay Permit</p>
  </div>
</a>

<a href="../Forms/Certifications.php" class="requestcard">
  <div class="requestboxicon">
    <img src="../picture/Clearance.png" alt="Icon" />
  </div>
  <div class="requestboxtext">
    <h3>Certifications</h3>
    <p>Residency, Indigency, Good Moral, First Time Jobseeker, Cedula</p>
  </div>
</a>

<a href="../Forms/Special_ID.php" class="requestcard">
  <div class="requestboxicon">
    <img src="../picture/Clearance.png" alt="Icon" />
  </div>
  <div class="requestboxtext">
    <h3>Specialized IDs</h3>
    <p>Senior Citizen, PWD, Solo Parent, and Barangay ID</p>
  </div>
</a>

<a href="../Forms/Medical_Assistance.php" class="requestcard">
  <div class="requestboxicon">
    <img src="../picture/Clearance.png" alt="Icon" />
  </div>
  <div class="requestboxtext">
    <h3>Medication Assistance</h3>
    <p>Free Barangay maintenance medicines and medical subsidies</p>
  </div>
</a>

<a href="../Forms/Incident_report.php" class="requestcard">
  <div class="requestboxicon">
    <img src="../picture/Clearance.png" alt="Icon" />
  </div>
  <div class="requestboxtext">
    <h3>Legal & Incident Reports</h3>
    <p>Blotter Record, Incident Report, Extra Judicial, Cohabitation</p>
  </div>
</a>
    </div>
  </div>
  <footer class="footer">
    <div class="footerleft"><div class="footertext">A Centralized Web-based Management System Service</div></div>
    <div class="footercenter">All Rights Reserved</div>
    <div class="footerright">Contact Info Part</div>
  </footer>
</main>
    </div>
  </body>
</html>