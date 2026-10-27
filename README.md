```{=html}
<p align="center">
```

`<img src="assets/images/logo/logo.png" alt="Smart Matrimony Logo" width="180">`{=html}

```{=html}
</p>
```

```{=html}
<h1 align="center">
```

Smart Matrimony

```{=html}
</h1>
```

```{=html}
<p align="center">
```

`<strong>`{=html}Matrimony \& Wedding Services Management
Platform`</strong>`{=html}

```{=html}
</p>
```

```{=html}
<p align="center">
```

A PHP and MySQL web application for creating matrimony profiles,
discovering compatible partners, and exploring wedding-related services.

```{=html}
</p>
```

```{=html}
<p align="center">
```

`<img src="https://img.shields.io/badge/PHP-Backend-777BB4?logo=php\\\&logoColor=white" alt="PHP">`{=html}
`<img src="https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql\\\&logoColor=white" alt="MySQL">`{=html}
`<img src="https://img.shields.io/badge/Bootstrap-Responsive%20UI-7952B3?logo=bootstrap\\\&logoColor=white" alt="Bootstrap">`{=html}
`<img src="https://img.shields.io/badge/Project-Educational-0F766E" alt="Educational Project">`{=html}

```{=html}
</p>
```

\---

## Table of Contents

* [About the Project](#about-the-project)
* [Key Features](#key-features)
* [Technology Stack](#technology-stack)
* [Screenshots](#screenshots)
* [Project Structure](#project-structure)
* [Getting Started](#getting-started)
* [Database](#database)
* [Configuration Notes](#configuration-notes)
* [Developer](#developer)
* [License](#license)

\---

## About the Project

**Smart Matrimony** is a web-based matrimony and wedding services
management system built with **PHP** and **MySQL**. It brings partner
discovery and wedding-service exploration together in one platform.

Users can register, build their profiles, discover potential matches
through search tools, save profiles, and communicate with other users.
The platform also includes wedding-service and package browsing,
supported by separate management areas for administrative and
service-related tasks.

The interface is designed for a responsive experience and supports
Bengali and English text in relevant parts of the application.

## Key Features

### Matrimony \& Profiles

* User registration and login
* Multi-step profile creation and profile management
* Partner discovery with search filters
* Profile matching and match-percentage display
* Bookmarking and managing interests
* User-to-user messaging
* Profile verification workflow

### Wedding Services

* Browse wedding-related services and packages
* View package details and pricing
* Search packages using budget-related filters
* Add packages to the cart
* Contact service providers or managers

### Dashboards \& Supporting Features

* Admin dashboard
* Manager dashboard for service and package management
* Authenticator dashboard for profile verification tasks
* Bengali and English interface text in supported areas
* Biodata viewing and PDF export where available
* Privacy controls for supported profile information and media

> Feature availability can depend on application configuration and the
> account role.

## Technology Stack

Technology   Role

\---

PHP          Server-side application logic
MySQL        Relational database
HTML5        Page structure
CSS3         Styling and responsive layouts
JavaScript   Client-side interactions
Bootstrap    UI components and responsive layout
XAMPP        Suggested local development environment

## Screenshots

The following screenshots are stored in the repository under
`screenshots/`.

### Login Page

```{=html}
<p align="center">
```

`<img src="screenshots/login.png" alt="Smart Matrimony login page" width="850">`{=html}

```{=html}
</p>
```

### Registration Page

```{=html}
<p align="center">
```

`<img src="screenshots/register.png" alt="Smart Matrimony registration page" width="850">`{=html}

```{=html}
</p>
```

### Dashboard

```{=html}
<p align="center">
```

`<img src="screenshots/dashboard.png" alt="Smart Matrimony dashboard" width="850">`{=html}

```{=html}
</p>
```

### Services Page

```{=html}
<p align="center">
```

`<img src="screenshots/services.png" alt="Smart Matrimony wedding services page" width="850">`{=html}

```{=html}
</p>
```

## Project Structure

The project contains the following main directories and files:

``` text
smart\\\_matrimony/
├── admin/
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
├── auth/
├── ajax/
├── booking/
├── chat/
├── config/
├── database/
│   └── smart\\\_matrimony.sql
├── includes/
├── matches/
├── profile/
├── search/
├── screenshots/
│   ├── login.png
│   ├── register.png
│   ├── dashboard.png
│   └── services.png
├── uploads/
├── index.php
├── login.php
├── register.php
└── dashboard.php
```

> This is a high-level overview. The exact directories and files may
> vary slightly by project version.

## Getting Started

### Prerequisites

Install a local PHP/MySQL environment such as
[XAMPP](https://www.apachefriends.org/), including: - Apache - MySQL or
MariaDB - A PHP version compatible with the project

### 1\. Clone the repository

``` bash
git clone https://github.com/jibonraihan/Smart-Matrimony.git
```

### 2\. Place the project in the web root

For a standard XAMPP installation, place the project folder in:

``` text
C:\\\\xampp\\\\htdocs\\\\smart\\\_matrimony\\\\
```

The folder name should match the URL you use locally.

### 3\. Start Apache and MySQL

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

### 4\. Create and import the database

1. Open phpMyAdmin at `http://localhost/phpmyadmin/`.
2. Create a database named `smart\\\_matrimony` (or use the database name
expected by your configuration).
3. Select the database and open the **Import** tab.
4. Import `database/smart\\\_matrimony.sql`.

**Important:** `database/smart\\\_matrimony.sql` contains the database
structure only; it does not include application records or sample user
data.

### 5\. Configure database access

Open the project's database configuration file (commonly
`config/db.php`) and verify the database host, name, username, and
password match your local MySQL setup.

Do not commit production credentials or private secrets to a public
repository.

### 6\. Open the application

Visit:

``` text
http://localhost/smart\\\_matrimony/
```

If the project is installed in a differently named folder, update the
URL accordingly.

## Database

The schema file is located at:

``` text
database/smart\\\_matrimony.sql
```

It defines the database structure. Because it contains no application
data, a fresh installation will not automatically include user accounts,
profiles, or service packages.

## Configuration Notes

* Ensure the `uploads/` directory and any application-managed upload
folders have the permissions required by your local environment.
* Configure the database connection before using the application.
* Third-party sign-in providers, if used, may require provider
credentials and valid callback URLs.
* For deployment, use HTTPS and keep credentials outside public source
control.

## Developer

**Jibon Raihan**

GitHub: [jibonraihan](https://github.com/jibonraihan)

Repository:
[Smart-Matrimony](https://github.com/jibonraihan/Smart-Matrimony)

## License

This project was developed for educational purposes. No separate
open-source license is specified in this README.

\---

```{=html}
<p align="center">
```

Made with care using PHP, MySQL, HTML, CSS, and JavaScript.

```{=html}
</p>
```



