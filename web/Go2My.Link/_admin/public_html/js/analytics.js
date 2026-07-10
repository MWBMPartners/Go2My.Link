/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 📊 Go2My.Link — Analytics Dashboard Charts (#42)
 * ============================================================================
 *
 * Renders every Chart.js chart on the analytics dashboard
 * (pages/analytics/index.php) from a same-origin JSON data island
 * (<script type="application/json" id="g2ml-analytics-data">) the page
 * writes via json_encode() — never by string-concatenating server data into
 * a <script> block. This file is loaded as an ordinary external, same-origin
 * <script src> so it runs under the site's script-src 'self' CSP with no
 * inline-script allowance required.
 *
 * Every chart is a companion to an always-present, server-rendered HTML
 * <table> inside a native <details> disclosure (WCAG 2.1 AA — see the page's
 * own docblock); this file only owns the *visual* chart layer, and does
 * nothing that the data tables do not already convey in text.
 *
 * Colours are read at render time via getComputedStyle() from the
 * --g2ml-chart-* custom properties defined in css/analytics.css (themed for
 * both [data-bs-theme="light"] and [data-bs-theme="dark"]) — never
 * hardcoded here — so charts re-theme correctly without any JS-side
 * light/dark branching.
 *
 * Dependencies: js/vendor/chart.umd.min.js (Chart.js 4.4.7, vendored locally
 * — no external CDN is used for this page).
 *
 * @package    Go2My.Link
 * @subpackage AdminDashboard
 * @version    1.1.0
 * @since      v1.1.0 — Phase 7 (#42)
 * ============================================================================
 */

(function ()
{
    'use strict';

    /**
     * Read a --g2ml-chart-* custom property's resolved value.
     *
     * @param  {string} propertyName
     * @return {string}
     */
    function readChartColor(propertyName)
    {
        var rawValue = getComputedStyle(document.documentElement).getPropertyValue(propertyName);

        if (typeof rawValue === 'string')
        {
            return rawValue.trim();
        }

        return '#888888';
    }

    /**
     * Whether the user has asked for reduced motion.
     *
     * @return {boolean}
     */
    function userPrefersReducedMotion()
    {
        if (window.matchMedia)
        {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        return false;
    }

    /**
     * The fixed, ordered categorical series palette (8 slots — see
     * css/analytics.css). Assigned by rank position within a single chart
     * render, never reassigned mid-render.
     *
     * @return {string[]}
     */
    function categoricalPalette()
    {
        return [
            readChartColor('--g2ml-chart-series-1'),
            readChartColor('--g2ml-chart-series-2'),
            readChartColor('--g2ml-chart-series-3'),
            readChartColor('--g2ml-chart-series-4'),
            readChartColor('--g2ml-chart-series-5'),
            readChartColor('--g2ml-chart-series-6'),
            readChartColor('--g2ml-chart-series-7'),
            readChartColor('--g2ml-chart-series-8')
        ];
    }

    /**
     * Parse the page's embedded JSON data island.
     *
     * @return {object|null}
     */
    function loadAnalyticsData()
    {
        var dataElement = document.getElementById('g2ml-analytics-data');

        if (dataElement === null)
        {
            return null;
        }

        try
        {
            return JSON.parse(dataElement.textContent);
        }
        catch (parseError)
        {
            return null;
        }
    }

    /**
     * Shared Chart.js options common to every chart on this page —
     * animation is disabled outright under prefers-reduced-motion, and
     * every text colour comes from the theme-aware ink tokens rather than a
     * hardcoded value.
     *
     * @return {object}
     */
    function sharedOptions()
    {
        var animationConfig = { duration: 400 };

        if (userPrefersReducedMotion() === true)
        {
            animationConfig = false;
        }

        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: animationConfig,
            plugins: {
                legend: {
                    labels: {
                        color: readChartColor('--g2ml-chart-ink-secondary')
                    }
                },
                tooltip: {
                    backgroundColor: readChartColor('--g2ml-chart-surface'),
                    titleColor: readChartColor('--g2ml-chart-ink-primary'),
                    bodyColor: readChartColor('--g2ml-chart-ink-secondary'),
                    borderColor: readChartColor('--g2ml-chart-baseline'),
                    borderWidth: 1
                }
            }
        };
    }

    /**
     * Shared x/y scale options for cartesian charts (line, bar). Doughnut
     * charts do not use this.
     *
     * @return {object}
     */
    function cartesianScales()
    {
        return {
            x: {
                ticks: {
                    color: readChartColor('--g2ml-chart-ink-muted')
                },
                grid: {
                    color: readChartColor('--g2ml-chart-gridline')
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    color: readChartColor('--g2ml-chart-ink-muted'),
                    precision: 0
                },
                grid: {
                    color: readChartColor('--g2ml-chart-gridline')
                }
            }
        };
    }

    /**
     * Render a single-series line chart (clicks over time).
     *
     * @param {string}   canvasID
     * @param {string[]} labels
     * @param {number[]} values
     * @param {string}   seriesLabel
     */
    function renderLineChart(canvasID, labels, values, seriesLabel)
    {
        var canvasElement = document.getElementById(canvasID);

        if (canvasElement === null)
        {
            return;
        }

        var seriesColor = readChartColor('--g2ml-chart-series-1');
        var options      = sharedOptions();

        options.scales = cartesianScales();
        options.plugins.legend.display = false;

        var context = canvasElement.getContext('2d');

        new Chart(context, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: seriesLabel,
                    data: values,
                    borderColor: seriesColor,
                    backgroundColor: seriesColor,
                    pointBackgroundColor: seriesColor,
                    borderWidth: 2,
                    pointRadius: 3,
                    tension: 0.15,
                    fill: false
                }]
            },
            options: options
        });
    }

    /**
     * Render a single-series bar chart (top links, scan sources).
     *
     * @param {string}   canvasID
     * @param {string[]} labels
     * @param {number[]} values
     * @param {string}   seriesLabel
     * @param {boolean}  horizontal
     */
    function renderBarChart(canvasID, labels, values, seriesLabel, horizontal)
    {
        var canvasElement = document.getElementById(canvasID);

        if (canvasElement === null)
        {
            return;
        }

        var seriesColor = readChartColor('--g2ml-chart-series-1');
        var options      = sharedOptions();

        options.scales = cartesianScales();
        options.plugins.legend.display = false;

        if (horizontal === true)
        {
            options.indexAxis = 'y';
        }

        var context = canvasElement.getContext('2d');

        new Chart(context, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: seriesLabel,
                    data: values,
                    backgroundColor: seriesColor,
                    borderColor: seriesColor,
                    borderWidth: 1
                }]
            },
            options: options
        });
    }

    /**
     * Render a categorical doughnut chart (browser / OS / device
     * breakdowns). Each slice takes the NEXT fixed categorical slot in rank
     * order — see categoricalPalette().
     *
     * @param {string}   canvasID
     * @param {string[]} labels
     * @param {number[]} values
     */
    function renderDoughnutChart(canvasID, labels, values)
    {
        var canvasElement = document.getElementById(canvasID);

        if (canvasElement === null)
        {
            return;
        }

        var palette      = categoricalPalette();
        var sliceColors  = [];
        var sliceIndex    = 0;

        while (sliceIndex < labels.length)
        {
            sliceColors.push(palette[sliceIndex % palette.length]);
            sliceIndex = sliceIndex + 1;
        }

        var options = sharedOptions();

        options.plugins.legend.display  = true;
        options.plugins.legend.position = 'bottom';

        var context = canvasElement.getContext('2d');

        new Chart(context, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: sliceColors,
                    borderColor: readChartColor('--g2ml-chart-surface'),
                    borderWidth: 2
                }]
            },
            options: options
        });
    }

    /**
     * Extract parallel label/value arrays from a
     * [{value|shortCode|scanSource|bucket, clicks|scans}] shaped list.
     *
     * @param  {Array}  items
     * @param  {string} labelKey
     * @param  {string} valueKey
     * @return {{labels: string[], values: number[]}}
     */
    function toSeries(items, labelKey, valueKey)
    {
        var labels = [];
        var values = [];

        if (Array.isArray(items) === false)
        {
            return { labels: labels, values: values };
        }

        var itemIndex = 0;

        while (itemIndex < items.length)
        {
            var item = items[itemIndex];

            labels.push(String(item[labelKey]));
            values.push(Number(item[valueKey]));

            itemIndex = itemIndex + 1;
        }

        return { labels: labels, values: values };
    }

    /**
     * Build every chart from the embedded analytics data. Safe to call
     * whether or not any given canvas/section exists on the page (each
     * render function no-ops when its canvas is absent — e.g. the "Top
     * Links" chart never renders on a single-link drill-down view).
     */
    function initialiseCharts()
    {
        if (typeof Chart === 'undefined')
        {
            return;
        }

        var analyticsData = loadAnalyticsData();

        if (analyticsData === null)
        {
            return;
        }

        if (analyticsData.clicksOverTime)
        {
            var clicksSeries = toSeries(analyticsData.clicksOverTime, 'bucket', 'clicks');

            renderLineChart('g2ml-chart-clicks-over-time', clicksSeries.labels, clicksSeries.values, 'Clicks');
        }

        if (analyticsData.topLinks)
        {
            var topLinksSeries = toSeries(analyticsData.topLinks, 'shortCode', 'clicks');

            renderBarChart('g2ml-chart-top-links', topLinksSeries.labels, topLinksSeries.values, 'Clicks', true);
        }

        if (analyticsData.breakdown)
        {
            if (analyticsData.breakdown.browserName)
            {
                var browserSeries = toSeries(analyticsData.breakdown.browserName, 'value', 'clicks');

                renderDoughnutChart('g2ml-chart-browser', browserSeries.labels, browserSeries.values);
            }

            if (analyticsData.breakdown.osName)
            {
                var osSeries = toSeries(analyticsData.breakdown.osName, 'value', 'clicks');

                renderDoughnutChart('g2ml-chart-os', osSeries.labels, osSeries.values);
            }

            if (analyticsData.breakdown.deviceType)
            {
                var deviceSeries = toSeries(analyticsData.breakdown.deviceType, 'value', 'clicks');

                renderDoughnutChart('g2ml-chart-device', deviceSeries.labels, deviceSeries.values);
            }

            if (analyticsData.breakdown.countryCode)
            {
                var countrySeries = toSeries(analyticsData.breakdown.countryCode, 'value', 'clicks');

                renderDoughnutChart('g2ml-chart-country', countrySeries.labels, countrySeries.values);
            }
        }

        if (analyticsData.scanSources)
        {
            var scanSeries = toSeries(analyticsData.scanSources, 'scanSource', 'scans');

            renderBarChart('g2ml-chart-scan-sources', scanSeries.labels, scanSeries.values, 'Scans', false);
        }
    }

    if (document.readyState === 'loading')
    {
        document.addEventListener('DOMContentLoaded', initialiseCharts);
    }
    else
    {
        initialiseCharts();
    }
})();
