from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import StaleElementReferenceException
import time

options = Options()
options.add_argument("--headless")
options.add_argument("--window-size=1280,900")

driver = webdriver.Chrome(options=options)

try:
    transport_modes = [
        "Car",
        "Motorcycle",
        "Public Transport (Mixed)",
        "On foot",
        "Bike"
    ]

    for mode in transport_modes:
        driver.get("http://localhost:8080/sustainable-travel-planner/calculator.php")
        
        # Wait for page to fully load
        time.sleep(1)

        origin_input = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.ID, "origin"))
        )
        origin_input.clear()
        origin_input.send_keys("Warsaw")

        destination_input = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.ID, "destination"))
        )
        destination_input.clear()
        destination_input.send_keys("Lodz")

        transport_select = Select(WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.ID, "transport"))
        ))
        transport_select.select_by_visible_text(mode)

        submit_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.TAG_NAME, "button"))
        )
        submit_button.click()

        # Wait until we have a result to read
        WebDriverWait(driver, 10).until(
            EC.visibility_of_element_located((By.CLASS_NAME, "carbon-card"))
        )
        
        # Ensure page finished updating
        time.sleep(1)

        # Retry logic for getting the result text
        max_attempts = 5
        for attempt in range(max_attempts):
            try:
                # Find the element fresh each time
                carbon_card = WebDriverWait(driver, 10).until(
                    EC.visibility_of_element_located((By.CLASS_NAME, "carbon-card"))
                )
                result_text = carbon_card.text.lower()
                
                if result_text.strip() != "":
                    break
            except StaleElementReferenceException:
                print(f"Encountered stale element on attempt {attempt+1}, retrying...")
                time.sleep(1)
        else:
            raise Exception(f"Could not safely fetch result text after {max_attempts} attempts")

        print(f"DEBUG ({mode}): {result_text}")

        if "co₂ emitted" in result_text or "0 kg" in result_text:
            print(f"Test Passed: Transport mode '{mode}' returned a result.")
        elif "no co₂ calculation available" in result_text:
            print(f"Test Passed: '{mode}' returned no result, but handled gracefully.")
        else:
            raise Exception(f"Unexpected result for transport mode: {mode}")

except Exception as e:
    print("Test Failed :", str(e))

finally:
    driver.quit()