<!DOCTYPE html>
<html lang="en">
   
<head>
   
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valora · top doctors & care</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

        :root {
            --n1: #F2F2F2; --n2: #E6E6E6; --n3: #DADADA; --n4: #C6C6C6;
            --n5: #9E9E9E; --n6: #6E6E6E; --n7: #3F3F3F; --n8: #1C1C1C;
            --maroon-50: #D8C9CE; --maroon-100: #C5A8B3; --maroon-200: #A56C7E;
            --maroon-300: #842646; --maroon-400: #7A2141; --maroon-500: #641732;
            --success-light: #C6D8D2; --success-primary: #39C37A; --success-deep: #2E955C;
            --warning-light: #E5D8C8; --warning-primary: #F48B05; --warning-deep: #B36805;
            --error-light: #E2D0CD; --error-primary: #F04233; --error-deep: #B03125;
            --info-light: #C9D3E6; --info-mid: #4C72B8; --info-primary: #0E3E9E; --info-deep: #082E73;
            --bg-body: var(--n1); --shadow-sm: 0 10px 30px -10px rgba(0,0,0,0.08);
            --shadow-lg: 0 20px 40px -12px rgba(100,23,50,0.15);
        }

        body { background-color: var(--bg-body); color: var(--n8); line-height: 1.5; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 2rem; }
        a { text-decoration: none; }

        .btn { display: inline-block; padding: 0.7rem 1.8rem; border-radius: 40px; font-weight: 600; transition: all 0.25s; border: none; cursor: pointer; font-size: 0.95rem; background: white; color: var(--n7); }
        .btn-primary { background: var(--maroon-300); color: white; }
        .btn-primary:hover { background: var(--maroon-400); transform: translateY(-3px); box-shadow: 0 14px 24px -8px var(--maroon-200); }
        .btn-outline-maroon { border: 2px solid var(--maroon-300); color: var(--maroon-300); background: transparent; }
        .btn-outline-maroon:hover { background: var(--maroon-300); color: white; }

        header { background: white; box-shadow: 0 2px 20px var(--n3); position: sticky; top: 0; z-index: 50; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1280px; margin: 0 auto; }
        .logo { font-size: 1.7rem; font-weight: 700; color: var(--maroon-300); letter-spacing: -0.3px; }
        .nav-links { display: flex; gap: 2.5rem; list-style: none; align-items: center; }
        .nav-links a { color: var(--n1); font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--maroon-300); }

        .hero { background: linear-gradient(112deg, white 0%, var(--n2) 100%); padding: 3.5rem 2rem; border-bottom: 1px solid var(--n3); }
        .hero-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; align-items: center; gap: 3rem; max-width: 1280px; margin: 0 auto; }
        .hero h1 { font-size: 3rem; font-weight: 800; color: var(--n8); line-height: 1.2; }
        .hero p { font-size: 1.1rem; color: var(--n6); margin: 1.2rem 0; max-width: 550px; }
        .hero-stats { display: flex; gap: 2.5rem; margin-top: 1.5rem; }
        .stat-item h3 { font-size: 2rem; color: var(--maroon-300); }
        .stat-item p { margin: 0; font-size: 0.95rem; color: var(--n7); }

        /* WHY CHOOSE US CARD */
        .hero-right { background: white; border-radius: 32px; padding: 2rem; box-shadow: var(--shadow-lg); border: 1px solid var(--maroon-50); }
        .why-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase; color: var(--maroon-300); margin-bottom: 8px; }
        .why-title { font-size: 1.5rem; font-weight: 700; color: var(--n8); margin-bottom: 1.4rem; line-height: 1.25; }
        .why-feature { display: flex; gap: 1rem; margin: 0.65rem 0; align-items: flex-start; padding: 0.85rem 1rem; border-radius: 16px; background: var(--n1); transition: background 0.2s, transform 0.15s; cursor: default; }
        .why-feature:hover { background: #f5e8ee; transform: translateX(4px); }
        .why-icon { width: 42px; height: 42px; background: var(--info-light); border-radius: 13px; display: flex; align-items: center; justify-content: center; color: var(--info-primary); font-size: 1rem; flex-shrink: 0; }
        .why-icon.maroon { background: #f5e8ee; color: var(--maroon-300); }
        .why-icon.green  { background: var(--success-light); color: var(--success-deep); }
        .why-icon.warn   { background: var(--warning-light); color: var(--warning-deep); }
        .why-text strong { display: block; font-size: 0.87rem; font-weight: 600; color: var(--n8); margin-bottom: 2px; }
        .why-text span { font-size: 0.79rem; color: var(--n6); line-height: 1.4; }
        .why-footer { margin-top: 1.1rem; padding-top: 1rem; border-top: 1px solid var(--n3); display: flex; align-items: center; gap: 8px; font-size: 0.77rem; color: var(--n6); }
        .why-footer i { color: var(--maroon-300); }

        .feature-chips { background: var(--n2); padding: 1.5rem 2rem; border-radius: 80px; margin: 2.5rem 0; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.2rem 2.5rem; border: 1px solid var(--n4); }
        .chip { font-weight: 600; color: var(--n7); font-size: 1.1rem; letter-spacing: -0.2px; }
        .chip i { margin-right: 6px; color: var(--maroon-200); }
        .chip.active { color: var(--maroon-400); border-bottom: 3px solid var(--warning-primary); }

        /* ===== TOP DOCTORS SECTION (new) ===== */
        .section-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--n8);
            margin: 3rem 0 1.5rem;
            position: relative;
        }
        .section-title:after {
            content: '';
            display: block;
            width: 70px;
            height: 4px;
            background: var(--maroon-300);
            margin-top: 0.4rem;
            border-radius: 4px;
        }
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }
        .doctor-card {
            background: white;
            border-radius: 30px;
            padding: 2rem 1.5rem 2rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: 0.2s ease;
            border: 1px solid var(--n3);
        }
        .doctor-card:hover {
            transform: translateY(-8px);
            border-color: var(--maroon-100);
            box-shadow: 0 30px 40px -20px var(--maroon-200);
        }
        .doc-img {
            width: 120px;
            height: 120px;
            background: linear-gradient(145deg, var(--maroon-50), var(--info-light));
            border-radius: 50%;
            margin: 0 auto 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--maroon-300);
            border: 4px solid white;
            box-shadow: 0 6px 16px var(--n4);
        }
        .doctor-card h3 {
            font-size: 1.5rem;
            color: var(--n8);
        }
        .doctor-card .specialty {
            color: var(--info-primary);
            font-weight: 600;
            margin: 0.3rem 0 0.8rem;
        }
        .doctor-card p {
            color: var(--n6);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        .profile-btn {
            display: inline-block;
            padding: 0.5rem 2rem;
            border-radius: 40px;
            background: var(--maroon-50);
            color: var(--maroon-400);
            font-weight: 600;
            transition: 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .profile-btn:hover {
            background: var(--maroon-300);
            color: white;
        }
        @media (max-width: 900px) {
            .doctors-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .doctors-grid { grid-template-columns: 1fr; }
        }

        .article-section { margin: 4rem 0; }
        .section-label { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
        .section-label h2 { font-size: 2rem; color: var(--n8); font-weight: 600; }
        .explore-link { color: var(--maroon-300); font-weight: 600; background: var(--maroon-50); padding: 0.5rem 1.8rem; border-radius: 40px; transition: 0.2s; }
        .explore-link:hover { background: var(--maroon-100); }
        .article-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 2rem;
            justify-items: center;
            max-width: 1280px;
            margin: 0 auto;
        }
        .article-card { background: white; border-radius: 28px; padding: 2rem 1.8rem; box-shadow: var(--shadow-sm); transition: 0.2s ease; border: 1px solid var(--n3); }
        .article-card:hover { transform: translateY(-8px); border-color: var(--maroon-100); box-shadow: 0 30px 40px -20px var(--maroon-200); }
        .article-badge { background: var(--info-light); color: var(--info-deep); font-size: 0.8rem; padding: 0.2rem 1rem; border-radius: 30px; width: fit-content; font-weight: 600; }
        .article-card h3 { font-size: 1.7rem; margin: 1rem 0 1.5rem; color: var(--n8); line-height: 1.3; }
        .read-now { color: var(--maroon-300); font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; border-bottom: 2px solid transparent; transition: 0.2s; }
        .read-now:hover { gap: 1rem; border-bottom-color: var(--warning-primary); }

        .management-banner { background: linear-gradient(145deg, var(--n1), white); border-radius: 60px; padding: 3rem; margin: 4rem 0; border: 1px solid var(--n4); box-shadow: inset 0 2px 8px var(--n2); }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; }
        .management-banner h4 { font-size: 1.8rem; color: var(--n8); margin-bottom: 1rem; }
        .bullet-list { list-style: none; }
        .bullet-list li { margin: 1rem 0; display: flex; align-items: center; gap: 0.8rem; font-size: 1.1rem; }
        .bullet-list i { font-size: 1.3rem; color: var(--maroon-300); }

        footer { background: var(--n7); color: var(--n2); padding: 2.5rem; text-align: center; margin-top: 5rem; border-top: 8px solid var(--maroon-300); }

        @media (max-width: 900px) {
            .hero-grid, .two-col { grid-template-columns: 1fr; }
            .article-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header>
    <nav>
        <a href="index.php" class="logo">Valora</a>
        <ul class="nav-links">
            <li><a href="login.php" class="btn btn-primary" style="padding:0.5rem 1.5rem;">Login</a></li>
               <li><a href="login.php" class="btn btn-primary" style="padding:0.5rem 1.5rem;">signup</a></li>
        </ul>
    </nav>
</header>

<section class="hero">
    <div class="hero-grid">
        <div>
            <h1>Care You Can Trust</h1>
            <p>At Valora, we believe healthcare should feel reassuring, refined, and deeply human. From the moment you walk through our doors, our focus is simple — to deliver medical excellence with clarity, compassion, and integrity.</p>
            <p>We are more than a hospital. We are a place where advanced medicine meets thoughtful design, where technology supports empathy, and where every patient is treated with dignity and respect.</p>
            <div class="hero-stats">
                <div class="stat-item"><h3>15k+</h3><p>daily records</p></div>
                <div class="stat-item"><h3>98%</h3><p>uptime</p></div>
                <div class="stat-item"><h3>24/7</h3><p>support</p></div>
            </div>
          
        </div>

        <!-- WHY CHOOSE US CARD -->
        <div class="hero-right">
            <div class="why-label">Why Valora</div>
            <div class="why-title">A Better Standard of Care.</div>

            <div class="why-feature">
                <div class="why-icon maroon"><i class="fas fa-user-md"></i></div>
                <div class="why-text">
                    <strong>Expert Specialists</strong>
                    <span>Board-certified physicians across 20+ specialties, committed to your best outcome.</span>
                </div>
            </div>

            <div class="why-feature">
                <div class="why-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="why-text">
                    <strong>Seamless Experience</strong>
                    <span>From booking to billing, every step is designed to be simple and stress-free.</span>
                </div>
            </div>

            <div class="why-feature">
                <div class="why-icon warn"><i class="fas fa-headset"></i></div>
                <div class="why-text">
                    <strong>Compassionate Support</strong>
                    <span>Our care team is available around the clock to guide and support you.</span>
                </div>
            </div>

            <div class="why-footer">
                <i class="fas fa-certificate"></i>
                Accredited by the International Healthcare Standards Board
            </div>
        </div>
    </div>
</section>

<div class="container">
    <div class="feature-chips">
        <span class="chip active"><i class="fas fa-home"></i> 10+ Years of Service</span>
        <span class="chip"><i class="fas fa-users"></i> 50+ Medical Staff</span>
        <span class="chip"><i class="fas fa-home"></i> 15 Departments</span>
        <span class="chip"><i class="fas fa-users"></i> 10,000+ Patients Served</span>
    </div>
</div>

<!-- ========== NEW TOP DOCTORS SECTION ========== -->
<div class="container">
    <h2 class="section-title">Our Top Specialists</h2>
    <div class="doctors-grid">
        <!-- Doctor 1 -->
        <div class="doctor-card">
            <div class="doc-img"><i class="fas fa-user-md"></i></div>
            <h3>Dr. Emilia Rios</h3>
            <div class="specialty">Cardiology · 22 years</div>
            <p>Dr. Emilia Rios is a senior consultant cardiologist specializing in interventional cardiology and minimally invasive heart procedures. With over two decades of experience, she has successfully treated thousands of patients with complex cardiovascular conditions. She is known for her precision, compassionate care, and leadership in cardiac rehabilitation programs..</p>
        </div>
        <!-- Doctor 2 -->
        <div class="doctor-card">
            <div class="doc-img"><i class="fas fa-user-nurse"></i></div>
            <h3>Dr. Jonathan Webb</h3>
            <div class="specialty">Orthopedics · Sports Medicine</div>
            <p>Dr. Jonathan Webb is an orthopedic specialist focusing on sports injuries, joint reconstruction, and minimally invasive orthopedic surgery. He has worked with professional athletes and is certified in advanced arthroscopic techniques. His patient-centered approach ensures faster recovery and long-term mobility improvement.</p>
        </div>
        <!-- Doctor 3 -->
        <div class="doctor-card">
            <div class="doc-img"><i class="fas fa-user-doctor"></i></div>
            <h3> Dr. Daniel Moreau</h3>
            <div class="specialty">Neurologist · Stroke & Brain Disorders Specialist</div>
            <p>Dr. Daniel Moreau is a board-certified neurologist specializing in stroke management, epilepsy, and neurodegenerative disorders. He is recognized for implementing advanced diagnostic techniques and comprehensive neurological rehabilitation plans. His multidisciplinary approach ensures personalized treatment for each patient.</p>
        </div>
    </div>
</div>

<div class="container">
    <h2 class="section-title">Our Top Specialists</h2>
    <div class="doctors-grid">
        <!-- Doctor 1 -->
        <div class="doctor-card">
            <div class="doc-img"><i class="fas fa-user-md"></i></div>
            <h3>Dr. Sophia Laurents</h3>
            <div class="specialty">Oncologist · Cancer Care Specialist </div>
            <p>Dr. Sophia Laurent is a leading oncology consultant with extensive experience in chemotherapy planning, targeted therapy, and early cancer detection programs. She is known for her patient-focused approach, combining advanced treatment protocols with compassionate long-term care strategies. Her dedication to research and clinical trials has contributed to significant advancements in cancer treatment at Valora.</p>
        </div>
        <!-- Doctor 2 -->
        <div class="doctor-card">
            <div class="doc-img"><i class="fas fa-user-nurse"></i></div>
            <h3>Dr. Ahmed Al-Mansoor</h3>
            <div class="specialty">Pulmonologist · Respiratory & Critical Care</div>
           <p>Dr. Ahmed Al-Mansoor specializes in respiratory medicine, asthma management, and critical care for complex lung conditions. With over 18 years of experience, he has successfully treated patients with chronic respiratory diseases and leads the hospital’s pulmonary rehabilitation program.
            </p>
        </div>
        <!-- Doctor 3 -->
        <div class="doctor-card">
            <div class="doc-img"><i class="fas fa-user-doctor"></i></div>
            <h3> Dr. Isabella Rossi</h3>
            <div class="specialty">Pathologist · Laboratory & Diagnostic Medicine</div>
            <p>Dr. Isabella Rossi oversees the hospital’s diagnostic laboratory services, ensuring accurate and timely test results. Her expertise includes advanced pathology analysis, clinical diagnostics, and quality assurance protocols that support precise medical decision-making across departments.</p>
        </div>
    </div>
</div>


<!-- explore more section (unchanged) -->
<div class="container article-section">
    <div class="section-label">
        <h2>Medical News & Expert Updates</h2>
    </div>
    <div class="article-grid">
        <div class="article-card">
            <div class="article-badge"><i class="far fa-user"> </i>Appointee</div>
            <h3>New Doctor Announcement</h3>
            <p style="color:var(--n6); margin-bottom:1.8rem;">We are pleased to announce that Dr. Ali, a senior oncology consultant with over 15 years of experience, has joined our medical team. he specializes in targeted therapy and comprehensive cancer care planning.</p>
           
        </div>
        <div class="article-card">
            <div class="article-badge" style="background:var(--warning-light); color:var(--warning-deep);"><i class="fas fa-stethoscope"></i> clinical</div>
            <h3>Protect Your Family This Flu Season</h3>
            <p style="color:var(--n6); margin-bottom:1.8rem;">Flu season is approaching, and prevention starts with timely vaccination. valora is offering seasonal flu vaccines for both children (6 months+) and adults in a safe and patient-friendly environment.</p>
       
        </div>
        <div class="article-card">
            <div class="article-badge" style="background:var(--success-light); color:var(--success-deep);"><i class="fas fa-headset"></i> Tip</div>
            <h3>Health Tip</h3>
            <p style="color:var(--n6); margin-bottom:1.8rem;">Maintaining a healthy heart begins with simple daily habits. Our cardiology team recommends 30 minutes of moderate exercise, a balanced low-sodium diet, and regular blood pressure monitoring. Early detection and lifestyle adjustments significantly reduce the risk of cardiovascular disease.</p>
            
        </div>
    </div>
</div>

<div class="container">
    <div class="management-banner">
        <div class="two-col">
            <div>
                <h4><i class="fas fa-chart-line" style="color:var(--maroon-300);"></i> Visit Us</h4>
                <ul class="bullet-list">
                    <li><i class="fas fa-location-arrow" style="color:var(--success-primary);"></i> Valora </li>
                    <li><i class="fas fa-home" style="color:var(--success-primary);"></i> Building 45, Health Avenue </li>
                    <li><i class="fas fa-home" style="color:var(--success-primary);"></i> Manama, Kingdom of Bahrain</li>
                </ul>
            </div>
            <div>
                <h4><i class="fas fa-clock" style="color:var(--info-primary);"></i> Working Hours</h4>
                <ul class="bullet-list">
                    <li><i class="fas fa-check-circle" style="color:var(--info-primary);"></i> Monday - Friday: 9:00 AM - 8:00 PM</li>
                    <li><i class="fas fa-check-circle" style="color:var(--info-primary);"></i> Friday – Saturday: Emergency Services Only</li>
                </ul>
            </div>
        </div>
        <div style="margin-top:2rem; background:var(--info-light); padding:1.2rem; border-radius:60px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;">
            <span><i class="fas fa-envelope" style="color:var(--info-deep);"></i> <strong>Email Us:</strong>info@Valora.com</span>
        
            <a href="#" class="btn" style="background:var(--maroon-300); color:white; padding:0.4rem 2rem;">+973 1700 1111</a>
        </div>
    </div>
</div>

<footer>
    <p style="font-size:1.2rem; margin-bottom:0.5rem;">Valora Medical Center</p>
    <p style="color:var(--n4);">This is a university project for educational purposes; all hospital information & services is  fictional.</p>
    <p style="margin-top:2rem;">&copy; 2026</p>
</footer>

<script src="js/main.js"></script>
</body>
</html>