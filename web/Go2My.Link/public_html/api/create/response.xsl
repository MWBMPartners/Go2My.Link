<?xml version="1.0" encoding="UTF-8"?>
<!--
  Copyright (c) 2024-2026 MWBM Partners Ltd (MWservices). All rights reserved.

  XSLT 1.0 stylesheet that renders the create-API <response> XML document into
  a small, self-contained, human-friendly HTML page. Browsers that receive the
  XML (via the xml-stylesheet processing instruction) apply this transform;
  programmatic clients consume the raw XML directly.
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/response">
        <html lang="en-GB">
            <head>
                <title>Go2My.Link — Create Short URL</title>
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <style type="text/css">
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                        margin: 0;
                        padding: 2rem;
                        background-color: #0d1117;
                        color: #e6edf3;
                    }
                    .card {
                        max-width: 40rem;
                        margin: 0 auto;
                        background-color: #161b22;
                        border: 1px solid #30363d;
                        border-radius: 0.75rem;
                        padding: 1.5rem 2rem;
                    }
                    h1 {
                        font-size: 1.25rem;
                        margin-top: 0;
                    }
                    .status-success {
                        color: #3fb950;
                    }
                    .status-error {
                        color: #f85149;
                    }
                    .short-url {
                        font-size: 1.1rem;
                        word-break: break-all;
                    }
                    .short-url a {
                        color: #58a6ff;
                    }
                    dl {
                        margin: 1rem 0 0 0;
                    }
                    dt {
                        font-weight: bold;
                        color: #8b949e;
                        margin-top: 0.75rem;
                    }
                    dd {
                        margin: 0.25rem 0 0 0;
                        word-break: break-all;
                    }
                </style>
            </head>
            <body>
                <div class="card">
                    <xsl:choose>
                        <xsl:when test="success = 'true'">
                            <h1 class="status-success">Short URL created</h1>
                            <p class="short-url">
                                <a href="{shortURL}">
                                    <xsl:value-of select="shortURL"/>
                                </a>
                            </p>
                            <dl>
                                <xsl:if test="shortCode != ''">
                                    <dt>Short code</dt>
                                    <dd>
                                        <xsl:value-of select="shortCode"/>
                                    </dd>
                                </xsl:if>
                            </dl>
                        </xsl:when>
                        <xsl:otherwise>
                            <h1 class="status-error">Request failed</h1>
                            <p>
                                <xsl:choose>
                                    <xsl:when test="error != ''">
                                        <xsl:value-of select="error"/>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:text>An unknown error occurred.</xsl:text>
                                    </xsl:otherwise>
                                </xsl:choose>
                            </p>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>
            </body>
        </html>
    </xsl:template>

</xsl:stylesheet>
