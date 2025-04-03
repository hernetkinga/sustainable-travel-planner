from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

options = Options()
options.add_argument("--headless")
options.add_argument("--window-size=1920,1080")

driver = webdriver.Chrome(options=options)

try:
    driver.get("http://localhost/sustainable-travel-planner/calculator.php")

    # Fill in garbage addresses
    origin_input = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.ID, "origin"))
    )
    origin_input.clear()
    origin_input.send_keys("###")

    destination_input = driver.find_element(By.ID, "destination")
    destination_input.clear()
    destination_input.send_keys("123456")

    # Choose Public Transport
    transport_select = Select(driver.find_element(By.ID, "transport"))
    transport_select.select_by_visible_text("Public Transport (Mixed)")

    # Submit the form
    driver.find_element(By.TAG_NAME, "button").click()

    # Wait for response
    result_box = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.CLASS_NAME, "carbon-card"))
    )

    result_text = result_box.text.lower()
    
    if "no co₂ calculation available" in result_text or "no route found" in result_text:
        print("Test Passed: Invalid input handled with a user-friendly message.")
    else:
        # Fail if it shows a result when it shouldn't
        raise Exception("Unexpected result for invalid input!")

except Exception as e:
    print("Test Failed:", str(e))

finally:
    driver.quit()
