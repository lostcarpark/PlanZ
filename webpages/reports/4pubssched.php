<?php
// Copyright (c) 2018 Peter Olszowka. All rights reserved. See copyright document for more details.
$report = [];
$report['name'] = 'Abridged Schedule Report';
$report['multi'] = 'true';
$report['output_filename'] = 'abridged_schedule.csv';
$report['description'] = 'Lists all Sessions Scheduled in all Rooms.';
$report['categories'] = array(
    'Publication Reports' => 15,
);
$report['queries'] = [];
$report['queries']['sessions'] =<<<'EOD'
SELECT
        S.sessionid, S.title, R.roomname, SCH.roomid, PS.pubstatusname, T.trackname,
        DATE_FORMAT(ADDTIME('$ConStartDatim$',SCH.starttime),'%a %l:%i %p') AS starttime,
        DATE_FORMAT(S.duration,'%i') AS durationmin, DATE_FORMAT(S.duration,'%k') AS durationhrs,
        KC.kidscatname
    FROM
             Sessions S
        JOIN Schedule SCH USING (sessionid)
        JOIN Rooms R USING (roomid)
        JOIN PubStatuses PS USING (pubstatusid)
        JOIN Tracks T USING (trackid)
        JOIN KidsCategories KC USING (kidscatid)
    ORDER BY
        SCH.starttime, R.roomname;
EOD;
$report['xsl'] =<<<'EOD'
<?xml version="1.0" encoding="UTF-8" ?>
<xsl:stylesheet version="1.1" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output encoding="UTF-8" indent="yes" method="html" />
    <xsl:include href="xsl/reportInclude.xsl" />
    <xsl:template match="/">
        <xsl:choose>
            <xsl:when test="doc/query[@queryName='sessions']/row">
                <table id="reportTable" class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Session ID</th>
                            <th>Title</th>
                            <th>Track</th>
                            <th>Room</th>
                            <th>StartTime</th>
                            <th>Duration</th>
                            <th>
                                <div>Publication</div>
                                <div>Status</div>
                            </th>
                            <th>Suitability for Children</th>
                        </tr>
                    </thead>
                    <tbody>
                        <xsl:apply-templates select="doc/query[@queryName='sessions']/row"/>
                    </tbody>
                </table>
            </xsl:when>
            <xsl:otherwise>
                <div class="alert alert-danger">No results found.</div>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    <xsl:template match="doc/query[@queryName='sessions']/row">
        <tr>
            <td>
				<xsl:call-template name="showSessionid">
					<xsl:with-param name="sessionid" select="@sessionid" />
				</xsl:call-template>
			</td>
            <td>
				<xsl:call-template name="showSessionTitle">
					<xsl:with-param name="sessionid" select="@sessionid" />
					<xsl:with-param name="title" select="@title" />
				</xsl:call-template>
			</td>
            <td><xsl:value-of select="@trackname" /></td>
            <td>
				<xsl:call-template name="showRoomName">
					<xsl:with-param name="roomid" select="@roomid" />
					<xsl:with-param name="roomname" select="@roomname" />
				</xsl:call-template>
			</td>
            <td><xsl:value-of select="@starttime" /></td>
            <td>
                <xsl:call-template name="showDuration">
                    <xsl:with-param name="durationhrs" select = "@durationhrs" />
                    <xsl:with-param name="durationmin" select = "@durationmin" />
                </xsl:call-template>
            </td>
            <td><xsl:value-of select="@pubstatusname" /></td>
            <td><xsl:value-of select="@kidscatname" /></td>
        </tr>
    </xsl:template>
</xsl:stylesheet>
EOD;
