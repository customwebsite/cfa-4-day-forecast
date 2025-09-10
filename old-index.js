const jsdom = require("jsdom");
const $ = require("jquery");
const axios = require("axios");
const express = require("express");
const app = express();
const PORT = process.env.PORT || 3000;

app.get("/", async (req, res) => {
  try {
    console.log("downloading the target web page");
    const axiosResponse = await axios.request({
      method: "GET",
      url: "https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/total-fire-bans-fire-danger-ratings/north-central-fire-district",
      headers: {
        "User-Agent":
          "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36",
      },
    });

    console.log("parsing html");
    
    $(new jsdom.JSDOM(axiosResponse.data).window);
    var table = $("#gvFireBansAndRatingsMunicipalityList");

    if (table) {
      const rows = table.querySelectorAll("tr");
      const data = [];

      rows.forEach((row) => {
        const cells = row.querySelectorAll("td");
        if (cells.length > 0) {
          data.push({
            municipality: cells[0].textContent,
            restrictions: cells[1].textContent,
            startDate: cells[2].textContent,
            endDate: cells[3].textContent,
          });
        }
      });

      res.json(data);
      console.log(data);
    } else {
      res.json({ error: "Table not found" });
    }
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

app.listen(PORT, "0.0.0.0", () => {
  console.log(`Server is running on port ${PORT}`);
});

async function main() {
  setInterval(async () => {
    console.log("keeping alive every 100 seconds");
  }, 100000);
}

main();
