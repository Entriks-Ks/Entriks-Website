# ENTRIKS Talent Hub

A professional talent sourcing platform connecting DACH companies with highly qualified professionals from Kosovo through nearshoring and active sourcing services.

## Overview

ENTRIKS Talent Hub bridges the skilled worker gap in the DACH region by offering:

- **Nearshoring** – Dedicated remote teams from Kosovo working fully integrated into your company structure
- **Active Sourcing** – Proactive talent acquisition targeting passive candidates across Europe

## Tech Stack

- **Backend**: PHP 8.2 with Apache
- **Database**: MongoDB
- **Frontend**: HTML5, CSS3, JavaScript (GSAP animations)
- **Email**: PHPMailer
- **Environment**: Docker support included

## Requirements

- PHP 8.2+
- MongoDB
- Apache with mod_rewrite enabled
- Composer

## Installation

### Local Development (XAMPP)

1. Clone the repository to your XAMPP htdocs folder:
   ```bash
   git clone <repository-url> /xampp/htdocs/ENTRIKS
   ```

2. Install PHP dependencies:
   ```bash
   cd backend
   composer install
   ```

3. Create a `.env` file in the `backend` directory with your configuration:
   ```env
   MONGODB_URI=mongodb://localhost:27017
   DB_NAME=entriks
   ```

4. Ensure MongoDB extension is enabled in `php.ini`:
   ```ini
   extension=mongodb
   ```

5. Access the site at `http://localhost/ENTRIKS`

### Docker

1. Build and run the Docker container:
   ```bash
   docker build -t entriks-talent-hub .
   docker run -p 8080:80 entriks-talent-hub
   ```

2. Access the site at `http://localhost:8080`

## Project Structure

```
ENTRIKS/
├── assets/                 # Frontend assets (CSS, JS, images)
│   ├── css/
│   ├── js/
│   └── img/
├── backend/               # Backend PHP files
│   ├── api/              # REST API endpoints
│   ├── blog/             # Blog management
│   ├── includes/         # Shared PHP functions
│   ├── vendor/           # Composer dependencies
│   ├── composer.json     # PHP dependencies
│   └── database.php      # Database connection
├── frontend/             # Frontend-specific files
├── php/                  # Email handler
├── index.php            # Main landing page
├── blog.php             # Blog page
├── agb.php              # Terms of service (DE)
├── datenschutz.php      # Privacy policy (DE)
├── impressum.php        # Imprint (DE)
└── Dockerfile           # Docker configuration
```

## Key Features

- **Bilingual Support** (German/English)
- **Blog System** with featured posts and categories
- **Contact Form** with email integration
- **Content Management** dashboard
- **SEO Optimized** structure
- **Responsive Design** with smooth animations
- **Cookie Consent** management

## Main Pages

| Page | Description |
|------|-------------|
| `index.php` | Landing page with services overview |
| `blog.php` | Blog listing and articles |
| `agb.php` | Terms and conditions (German) |
| `datenschutz.php` | Privacy policy (German) |
| `impressum.php` | Legal imprint (German) |

## Backend Admin Features

- Blog post management (create, edit, delete, publish)
- Image upload and management
- User account settings
- Dashboard analytics
- Static content caching

## Environment Variables

Create a `.env` file in the `backend` directory:

```env
MONGODB_URI=mongodb://localhost:27017
DB_NAME=entriks
SMTP_HOST=smtp.example.com
SMTP_USER=your-email@example.com
SMTP_PASS=your-password
SMTP_PORT=587
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

Private – All rights reserved by ENTRIKS Group.

## Contact

- **Website**: [entriks.com](https://entriks.com)
- **Email**: info@entriks.com
- **Phone**: +383 43 889 344
- **Address**: Lot Vaku L2.1, 10000 Pristina, Kosovo

---

Part of the [ENTRIKS Group](https://entriks.com)
