from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException
import time

options = Options()
options.add_argument("--headless")
options.add_argument("--window-size=1280,900")

driver = webdriver.Chrome(options=options)

try:
    driver.get("http://localhost/sustainable-travel-planner/calculator.php")

    # Fill in the form
    origin_input = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, "origin"))
    )
    origin_input.clear()
    origin_input.send_keys("Warsaw")

    destination_input = driver.find_element(By.ID, "destination")
    destination_input.clear()
    destination_input.send_keys("Krakow")

    transport_select = Select(driver.find_element(By.ID, "transport"))
    transport_select.select_by_visible_text("Public Transport (Mixed)")

    driver.find_element(By.TAG_NAME, "button").click()

    # Wait for weather section
    try:
        weather_box = WebDriverWait(driver, 12).until(
            EC.presence_of_element_located((By.CLASS_NAME, "weather-box"))
        )
        print("Weather data displayed.")
    except TimeoutException:
        print("Weather data not found.")

    # Wait for transit schedule items
    try:
        schedule_items = WebDriverWait(driver, 10).until(
            EC.presence_of_all_elements_located((By.CSS_SELECTOR, ".schedule-container ul li"))
        )
        if len(schedule_items) > 0:
            print(f" Transit schedule displayed with {len(schedule_items)} item(s).")
        else:
            print("Transit schedule block present but empty.")
    except TimeoutException:
        print("Transit schedule not found.")

except Exception as e:
    print("Test Failed ❌:", str(e))

finally:
    driver.quit()
