from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# Setup browser options
options = Options()
options.add_argument("--headless")
options.add_argument("--window-size=1920,1080")

driver = webdriver.Chrome(options=options)

try:
    # Open your calculator page
    driver.get("http://localhost:8080/sustainable-travel-planner/calculator.php") 

    # Find transport dropdown and leave it as default (e.g. Car)
    transport_select = Select(driver.find_element(By.ID, "transport"))
    assert transport_select.first_selected_option.text == "Car"

    # Submit the form with empty fields
    driver.find_element(By.TAG_NAME, "button").click()

    # Wait for page to reload and check result area
    message = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.XPATH, "//div[contains(@class,'carbon-card')]"))
    )

    # Look for message like "No CO₂ calculation available."
    text = message.text
    assert "No CO₂ calculation available" in text or "CO₂ emitted for this journey" not in text

    print("Test Passed: Empty input handled correctly")

except Exception as e:
    print("Test Failed:", str(e))

finally:
    driver.quit()
