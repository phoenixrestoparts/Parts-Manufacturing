.vwpm-wrap {
    max-width: 1200px;
}

.vwpm-card {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    margin: 20px 0;
    padding: 20px;
}

.vwpm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.vwpm-stat-card {
    background: #f0f0f1;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.vwpm-stat-label {
    display: block;
    color: #646970;
    margin-bottom: 10px;
    font-weight: 600;
}

.vwpm-stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
}

.vwpm-supplier-box {
    position: relative;
}

.vwpm-search-results {
    position: absolute;
    width: 100%;
    max-width: 420px;
    background: #fff;
    border: 1px solid #dcdcde;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    z-index: 999;
    display: none;
}

.vwpm-search-results.active {
    display: block;
}

.vwpm-search-result-item {
    padding: 10px 12px;
    border-bottom: 1px solid #f0f0f1;
    cursor: pointer;
}

.vwpm-search-result-item:hover {
    background: #f6f7f7;
}

.vwpm-supplier-selected-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    padding: 10px;
}

.vwpm-results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
}

.vwpm-results-table th,
.vwpm-results-table td {
    border: 1px solid #dcdcde;
    padding: 10px;
    text-align: left;
}

.vwpm-results-table th {
    background: #f6f7f7;
}

.vwpm-subtotal-row td {
    font-weight: 600;
}

.vwpm-grand-total-row td {
    font-weight: 700;
}

.vwpm-tool-summary {
    margin-top: 20px;
}
