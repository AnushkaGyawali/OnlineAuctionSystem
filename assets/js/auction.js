const endTime = new Date(document.getElementById("endTime").value).getTime();

setInterval(() => {
    const now = new Date().getTime();
    const diff = endTime - now;

    if (diff <= 0) {
        document.getElementById("timer").innerText = "Auction Ended";
    } else {
        const minutes = Math.floor(diff / 60000);
        document.getElementById("timer").innerText = minutes + " minutes left";
    }
}, 1000);
