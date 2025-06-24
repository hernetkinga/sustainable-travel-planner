from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time

# Setup Chrome (you can switch to Firefox, Edge, etc.)
options = Options()
options.add_argument("--headless")  # run in background (optional)
options.add_argument("--window-size=1920,1080")

# Update path if needed
driver = webdriver.Chrome(options=options)

try:
    driver.get("http://localhost:8080/sustainable-travel-planner/calculator.php")

    # Fill out the origin
    origin_input = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, "origin"))
    )
    origin_input.clear()
    origin_input.send_keys("Warsaw")

    # Fill out the destination
    destination_input = driver.find_element(By.ID, "destination")
    destination_input.clear()
    destination_input.send_keys("Krakow")

    # Select transport mode
    transport_select = Select(driver.find_element(By.ID, "transport"))
    transport_select.select_by_visible_text("Car")

    # Submit the form
    driver.find_element(By.TAG_NAME, "button").click()

    # Wait for CO2 result to appear (up to 10 seconds)
    co2_result = WebDriverWait(driver, 10).until(
        EC.visibility_of_element_located((By.CLASS_NAME, "co2-result"))
    )

    print("Test Passed: CO₂ result displayed ->", co2_result.text)

except Exception as e:
    print("Test Failed:", str(e))

finally:
    driver.quit()
