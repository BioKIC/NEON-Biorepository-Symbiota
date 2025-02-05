/***
 * NEON Barcode-only Custom Styles
 * Author: Ed Gilbert
 * Version: Sept 2023
 *
 * Features:
 * - Replaces standard Symbiota barcode with custom (using NEON tag (barcode) instead of catalogNumber)
 * - Barcodes courtesy of barcode.tec-it.com
 * - Removes all other identifiers except for IGSN
 */

let labels = document.querySelectorAll(".label");
labels.forEach((label) => {
	let catNums = label.querySelector(".other-catalog-numbers");
	let catNumsText = catNums.innerText;
	let bcSrc = label.querySelector(".cn-barcode img");
	let match = catNumsText.match(/NEON sampleID:\s*([\w.-]+)/);
    if (match) {
        catNums.innerHTML = match[1];
    }

	let newBcSrc = '';
	let cArr = catNumsText.split(";");
	cArr.forEach((catNum) => {
		if (catNum.includes("barcode")) {
			let barcode = catNum.match(/(?<=barcode\): ).*/)[0].trim();
            newBcSrc = "getBarcodeCode128.php?bcheight=40&bctext=" + barcode;
			return true;
		}
	});
	bcSrc.src = newBcSrc;
});