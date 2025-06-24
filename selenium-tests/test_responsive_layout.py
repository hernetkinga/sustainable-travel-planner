from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

options = Options()
options.add_argument("--window-size=375,812")  # iPhone X dimensions

driver = webdriver.Chrome(options=options)

try:
    driver.get("http://localhost:8080/sustainable-travel-planner/calculator.php")

    # Wait for form to load
    form = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, "form"))
    )

    # Check if origin and destination inputs are stacked (basic layout check)
    origin_input = driver.find_element(By.ID, "origin")
    destination_input = driver.find_element(By.ID, "destination")

    origin_location = origin_input.location
    destination_location = destination_input.location

    # If vertical layout: destination should be visibly lower than origin
    if destination_location['y'] > origin_location['y']:
        print("Test Passed: Inputs are stacked vertically (mobile-friendly layout).")
    else:
        raise Exception("Inputs not stacked — layout may not be mobile responsive.")

    # Check if submit button is visible
    button = driver.find_element(By.TAG_NAME, "button")
    assert button.is_displayed()

    # Optional: save screenshot
    driver.save_screenshot("mobile_layout_test.png")

except Exception as e:
    print("Test Failed:", str(e))

finally:
    driver.quit()
