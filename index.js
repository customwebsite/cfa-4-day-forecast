const HTMLParser = require("node-html-parser");
const axios = require("axios");
const express = require("express");
const app = express();
const PORT = process.env.PORT || 3000;

app.get("/", async (req, res) => {
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
  var html = HTMLParser.parse(axiosResponse.data);
  console.log(axiosResponse.data);
  var ele = html.getElementById("gvFireBansAndRatingsMunicipalityList");
  //console.log(ele);
});

app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`);
});

async function main() {
  setInterval(async () => {
    console.log("keeping alive every 100 seconds");
  }, 100000);
}

main();
