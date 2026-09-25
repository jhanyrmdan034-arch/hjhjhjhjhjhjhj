package ir.omid.vpnman.model

data class VpnServer(
    val id: String,
    val name: String,
    val protocol: String,
    val config: String,
    val sourceName: String = ""
)

data class PreConnectAd(
    val id: Int,
    val title: String,
    val imageUrl: String,
    val targetUrl: String?,
    val displaySeconds: Int,
    val showBeforeConnect: Boolean
)

data class ManifestPayload(
    val maintenance: Boolean,
    val minimumAppVersion: String,
    val servers: List<VpnServer>,
    val ad: PreConnectAd?
)

enum class ConnectionState { DISCONNECTED, CONNECTING, CONNECTED, DISCONNECTING, ERROR }
