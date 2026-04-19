<?php
session_start();
// If the user is already logged in, redirect them straight to the dashboard.
if (isset($_SESSION['UserID'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paulvante - Sustainable Agriculture</title>
    <style>
        /* CSS Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #0b2214; /* Deep dark green background */
            color: #ffffff;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Navigation Bar */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 60px;
            position: absolute;
            top: 0;
            width: 100%;
            z-index: 10;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .nav-links a {
            font-size: 14px;
            padding: 8px 18px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .nav-links a.active {
            background-color: #c5ff32; /* Lime green accent */
            color: #0b2214;
            font-weight: 600;
        }

        .btn-contact {
            background-color: #c5ff32;
            color: #0b2214;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        
        .btn-contact:hover {
            background-color: #a8e022;
        }

        /* Hero Section */
        .hero {
            position: relative;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 10%;
            /* Placeholder agriculture background image */
            background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.5)), 
                        url('https://images.unsplash.com/photo-1625246333195-78d9c38ad449?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover no-repeat;
        }

        .hero-content {
            max-width: 800px;
            margin-top: 50px;
        }

        .badge {
            display: inline-block;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(4px);
        }

        .hero h1 {
            font-size: 65px;
            line-height: 1.1;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .hero p {
            font-size: 16px;
            color: #e0e0e0;
            line-height: 1.6;
            margin-bottom: 35px;
            margin-left: auto;
            margin-right: auto;
            max-width: 600px;
        }

        .btn-explore {
            display: inline-block;
            background-color: #c5ff32;
            color: #0b2214;
            padding: 15px 35px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 15px;
            transition: 0.3s;
        }
        
        .btn-explore:hover {
            background-color: #a8e022;
        }

        /* Logos Banner */
        .logos {
            background-color: #07160d;
            padding: 30px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            flex-wrap: wrap;
            gap: 20px;
        }

        .logos span {
            color: #ffffff;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.8;
        }

        /* Info Section */
        .innovating-section {
            padding: 80px 60px;
            background-color: #0b2214;
        }

        .innovating-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 50px;
        }

        .innovating-header h2 {
            font-size: 40px;
            line-height: 1.2;
            max-width: 500px;
            font-weight: 500;
        }

        .innovating-header p {
            font-size: 14px;
            color: #9aa39e;
            max-width: 350px;
            line-height: 1.6;
        }

        .innovating-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 60px;
        }

        .image-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .image-grid img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 12px;
        }

        .stats-column {
            display: flex;
            flex-direction: column;
            gap: 50px;
            justify-content: flex-start;
        }

        .stat-item h3 {
            font-size: 55px;
            color: #ffffff;
            margin-bottom: 5px;
            font-weight: 400;
        }

        .stat-item h4 {
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-item p {
            font-size: 14px;
            color: #9aa39e;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <nav>
        <div class="logo">PAULVANTE</div>
        <div class="nav-links">
            <a href="#" class="active">Home</a>
            <a href="#">About Us</a>
            <a href="#">Our Farms</a>
            <a href="#">Products</a>
            <a href="#">Sustainability</a>
            <a href="#">Blog</a>
        </div>
        <a href="auth.php" class="btn-contact">Sign In &#8599;</a>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <div class="badge">Sustaining Earth</div>
            <h1>Grow the Future with Sustainable Agriculture</h1>
            <p>We empower farmers with eco-friendly methods, modern tools, and a shared mission to nourish the planet naturally and responsibly.</p>
            <a href="auth.php" class="btn-explore">Access ERP Dashboard &#8599;</a>
        </div>
    </section>

    <section class="logos">
        <span>&#9672; FocalPoint</span>
        <span>&#9716; Screentime</span>
        <span>&#9685; Segment</span>
        <span>&#10036; Shutterframe</span>
        <span>&#9681; Lightspeed</span>
        <span>&#10070; Mastermail</span>
    </section>

    <section class="innovating-section">
        <div class="innovating-header">
            <h2>INNOVATING THE FUTURE OF AGRICULTURE</h2>
            <p>AgriGrow combines modern technology and sustainable methods to help farmers grow smarter, faster, and greener.</p>
        </div>
        
        <div class="innovating-content">
            <div class="image-grid">
                <img src="https://images.unsplash.com/photo-1586771107445-d3ca888129ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Agriculture Field">
                <img src="https://images.unsplash.com/photo-1595841696677-6489ff3f8cd1?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Farmer">
            </div>

            <div class="stats-column">
                <div class="stat-item">
                    <h3>100%</h3>
                    <h4>Customer Satisfaction</h4>
                    <p>We create solutions that farmers trust and rely on.</p>
                </div>
                <div class="stat-item">
                    <h3>20+</h3>
                    <h4>Years of Experience</h4>
                    <p>Decades of innovation driving progress in global agriculture.</p>
                </div>
                <div class="stat-item">
                    <h3>100%</h3>
                    <h4>Eco-Friendly</h4>
                    <p>Committed to sustainable practices that protect the earth.</p>
                </div>
            </div>
        </div>
    </section>

</body>
</html>