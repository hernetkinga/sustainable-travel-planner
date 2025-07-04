# Sustainable Travel Planner

Sustainable Travel Planner is a web application that helps users plan trips while minimizing their carbon footprint. It calculates CO₂ emissions for different types of transport and integrates external APIs to provide useful, real-time travel information.


## Requirements

To run this project locally, you will need:

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/)
- API keys:
  - **Google Maps API** 
  - **WeatherAPI** 
- PHP >= 8.0 [https://www.php.net/downloads.php](https://www.php.net/downloads.php)
- PHP Extensions:
  - `curl`
  - `json`
  - `mbstring`
  - `openssl`
  - `pdo`
  - `pdo_mysql`
  - `fileinfo`
  - `opcache`

## Local Development (Docker)

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/sustainable-travel-planner.git
cd sustainable-travel-planner 
```

### 2. Create a Configuration File

Copy the example configuration file and add your own API keys:

```bash
cp config/config.example.php config/config.php
```

### 3. Build and Run the Application

```bash
docker build -t travel-planner .
docker run -p 8080:8080 -e PORT=8080 travel-planner
```
Once running, visit the app in your browser: http://localhost:8080

## How to obtain API keys

The `config/config.php` file contains private API keys and is ignored via `.gitignore`.
To run the app locally, copy `config/config.example.php` as `config.php` and enter your own keys as shown above in step 2 in Local Development section.

### Google Maps API

1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and log in.
2. Create a new project.
3. Enable the following APIs:
   - **Maps JavaScript API**
   - **Directions API**
4. Go to "Credentials" and generate a new API key.
5. Copy your API key and paste it into the `config/config.php` file under `'google_maps_api_key'`.

### WeatherAPI

1. Sign up at [weatherapi.com](https://www.weatherapi.com/).
2. Log in and go to your dashboard.
3. Generate a new API key.
4. Copy your API key and paste it into the `config/config.php` file under `'weather_api_key'`.

## How to run tests

### PHP Unit Tests

To run unit tests for classes like CarbonCalculator, GoogleMapsAPI, and WeatherAPI, make sure you have PHPUnit installed.

Requirements:
- PHP >= 8.0
- PHPUnit (recommended: installed via Composer or globally)

### Run tests

```bash
# If installed globally
phpunit tests/

# Or with Composer (if you use composer.json and PHPUnit as a dev dependency)
./vendor/bin/phpunit tests/
```

Example command:
```bash
phpunit tests/CarbonCalculatorTest.php
```

### Selenium UI Tests (Python)
Requirements:
- Python 3.7+
- Google Chrome
- ChromeDriver

```bash
pip install selenium
```

Run a single test:
```bash
python selenium-tests/test_empty_form_submission.py
```

To run all tests:
```bash
python -m unittest discover -s selenium-tests
```