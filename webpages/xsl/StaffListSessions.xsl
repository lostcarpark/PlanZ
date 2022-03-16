<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<!-- File created by Peter Olszowka July 16, 2020
Copyright (c) 2020 Peter Olszowka. All rights reserved. See copyright document for more details. -->
    <xsl:param name="showLinks" />
    <xsl:param name="now" />
    <xsl:param name="trackIsPrimary" />
    <xsl:param name="showTrack" />
    <xsl:param name="showTags" />
    <xsl:template match="/">
        <xsl:choose>
            <xsl:when test="not(/doc/query[@queryName='schedule']/row)">
                <div class="alert-info">No matching results found.</div>
            </xsl:when>
            <xsl:otherwise>
                <div class="col-sm-6 offset-sm-3 mt-3" role="alert">
                    <h4 class="alert alert-success">Generated by PlanZ: <xsl:value-of select="$now" /></h4>
                </div>
                <p>If a room name and time are listed, then the session is on the schedule; otherwise, not.</p>
                <hr />
                <div class="row">
                    <xsl:choose>
                        <xsl:when test="$trackIsPrimary">
                            <div class="col-0p75 pr-0">Session ID</div>
                            <div class="col-2">Track</div>
                            <div class="col-3">Title</div>
                            <div class="col-1p75">Type</div>
                            <div class="col-1p25">Status</div>
                            <div class="col-1p5">Room</div>
                            <div class="col-1">When</div>
                            <div class="col-0p75">Expand</div>
                        </xsl:when>
                        <xsl:otherwise>
                            <div class="col-0p75 pr-0">Session ID</div>
                            <div class="col-3">Tags</div>
                            <div class="col-3">Title</div>
                            <div class="col-1p5">Type</div>
                            <div class="col-1">Status</div>
                            <div class="col-1">Room</div>
                            <div class="col-1">When</div>
                            <div class="col-0p75">Expand</div>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>
                <xsl:apply-templates select="doc/query[@queryName='schedule']/row" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template match="doc/query[@queryName='schedule']/row" >
        <div class="card my-2 px-1 schedule-card">
            <xsl:choose>
                <xsl:when test="$trackIsPrimary">
                    <div class="row">
                        <div class="col-0p75">
                            <xsl:choose>
                                <xsl:when test="$showLinks">
                                    <a href="StaffAssignParticipants.php?selsess={@sessionid}" >
                                        <xsl:value-of select="@sessionid" />
                                    </a>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="@sessionid" />
                                </xsl:otherwise>
                            </xsl:choose>
                        </div>
                        <div class="col-2"><xsl:value-of select="@trackname" /></div>
                        <div class="col-3">
                            <xsl:choose>
                                <xsl:when test="$showLinks">
                                    <a href="EditSession.php?id={@sessionid}" >
                                        <xsl:value-of select="@title" />
                                    </a>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="@title" />
                                </xsl:otherwise>
                            </xsl:choose>
                        </div>
                        <div class="col-1p75"><xsl:value-of select="@typename" /></div>
                        <div class="col-1p25"><xsl:value-of select="@statusname" /></div>
                        <div class="col-1p5"><xsl:value-of select="@roomname" /></div>
                        <div class="col-1p25"><xsl:value-of select="@starttime" /></div>
                        <div class="col-0p5 expander-wrapper">
                            <a href="#collapse-{@sessionid}" data-toggle="collapse" class="collapsed" aria-expanded="true"
                               aria-controls="#collapse-{@sessionid}">
                                <div class="expander">&#x2304;</div>
                            </a>
                        </div>
                    </div>
                    <div id="collapse-{@sessionid}" class="collapse list-group list-group-flush">
                        <div class="list-group-item py-1">
                            <div class="row">
                                <xsl:if test="$showTags">
                                    <div class="col-offset-1 col-1">Tags:</div>
                                    <div class="col-3"><xsl:value-of select="@taglist" /></div>
                                </xsl:if>
                                <div class="col-offset-1 col-1">Duration:</div>
                                <div class="col-2"><xsl:value-of select="@duration" /></div>
                            </div>
                            <div class="row">
                                <div class="col-offset-1 col-1">Description:</div>
                                <div class="col-4"><xsl:value-of select="@progguiddesc" /></div>
                                <div class="col-offset-1 col-1p5">Prospective Participant Info:</div>
                                <div class="col-4"><xsl:value-of select="@persppartinfo" /></div>
                            </div>
                        </div>
                    </div>
                </xsl:when>
                <xsl:otherwise>
                    <div class="row">
                        <div class="col-0p75">
                            <xsl:choose>
                                <xsl:when test="$showLinks">
                                    <a href="StaffAssignParticipants.php?selsess={@sessionid}" >
                                        <xsl:value-of select="@sessionid" />
                                    </a>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="@sessionid" />
                                </xsl:otherwise>
                            </xsl:choose>
                        </div>
                        <div class="col-3"><xsl:value-of select="@taglist" /></div>
                        <div class="col-3">
                            <xsl:choose>
                                <xsl:when test="$showLinks">
                                    <a href="EditSession.php?id={@sessionid}" >
                                        <xsl:value-of select="@title" />
                                    </a>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select="@title" />
                                </xsl:otherwise>
                            </xsl:choose>
                        </div>
                        <div class="col-1p5"><xsl:value-of select="@typename" /></div>
                        <div class="col-1"><xsl:value-of select="@statusname" /></div>
                        <div class="col-1"><xsl:value-of select="@roomname" /></div>
                        <div class="col-1p25"><xsl:value-of select="@starttime" /></div>
                        <div class="col-0p5 expander-wrapper">
                            <a href="#collapse-{@sessionid}" data-toggle="collapse" class="collapsed" aria-expanded="true"
                                aria-controls="#collapse-{@sessionid}">
                                <div class="expander">&#x2304;</div>
                            </a>
                        </div>
                    </div>
                    <div id="collapse-{@sessionid}" class="collapse list-group list-group-flush">
                        <div class="list-group-item py-1">
                            <div class="row">
                                <xsl:if test="$showTrack">
                                    <div class="col-offset-1 col-1">Track:</div>
                                    <div class="col-2"><xsl:value-of select="@trackname" /></div>
                                </xsl:if>
                                <div class="col-offset-1 col-1">Duration:</div>
                                <div class="col-2"><xsl:value-of select="@duration" /></div>
                            </div>
                            <div class="row">
                                <div class="col-offset-1 col-1">Description:</div>
                                <div class="col-4"><xsl:value-of select="@progguiddesc" /></div>
                                <div class="col-offset-1 col-1p5">Prospective Participant Info:</div>
                                <div class="col-4"><xsl:value-of select="@persppartinfo" /></div>
                            </div>
                        </div>
                    </div>
                </xsl:otherwise>
            </xsl:choose>
        </div>
    </xsl:template>
</xsl:stylesheet>