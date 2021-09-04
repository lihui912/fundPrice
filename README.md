# Intro
This is a small script to grab the daily fund price from my Unit Trust broker website, parse it and display the result on the terminal.

The broker's URL and its fund code were removed from this repo. The random 0.1s to 1s sleep was added between each request send to the website to prevent concentration.

# Usage
1. Edit `fundList.php` to add your fund's code.
2. Run `php fundPrice.php`, the script will grab your fund's data and display them.
3. Enjoy!