package ir.omid.vpnman.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import ir.omid.vpnman.data.LatencyTester
import ir.omid.vpnman.data.VpnPanelApi
import ir.omid.vpnman.model.PreConnectAd
import ir.omid.vpnman.model.VpnServer
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Semaphore
import kotlinx.coroutines.sync.withPermit

data class HomeUiState(
    val loading: Boolean = true,
    val refreshing: Boolean = false,
    val servers: List<VpnServer> = emptyList(),
    val selectedServerId: String? = null,
    val latencies: Map<String, Int?> = emptyMap(),
    val ad: PreConnectAd? = null,
    val maintenance: Boolean = false,
    val minimumVersion: String = "1.0.0",
    val error: String? = null
) {
    val selectedServer: VpnServer? get() = servers.firstOrNull { it.id == selectedServerId } ?: servers.firstOrNull()
}

class MainViewModel : ViewModel() {
    private val api = VpnPanelApi()
    private val _ui = MutableStateFlow(HomeUiState())
    val ui: StateFlow<HomeUiState> = _ui.asStateFlow()
    private var manuallySelected = false

    init { refresh(false) }

    fun refresh(userInitiated: Boolean = true) {
        viewModelScope.launch {
            _ui.update { it.copy(loading = !userInitiated && it.servers.isEmpty(), refreshing = userInitiated, error = null) }
            api.fetchManifest().fold(
                onSuccess = { manifest ->
                    val supported = manifest.servers.filter { it.protocol in setOf("vless", "vmess", "trojan", "ss") }
                    _ui.update {
                        it.copy(
                            loading = false,
                            refreshing = false,
                            servers = supported,
                            selectedServerId = it.selectedServerId?.takeIf { id -> supported.any { s -> s.id == id } }
                                ?: supported.firstOrNull()?.id,
                            ad = manifest.ad,
                            maintenance = manifest.maintenance,
                            minimumVersion = manifest.minimumAppVersion,
                            error = if (supported.isEmpty() && !manifest.maintenance) "سرور قابل پشتیبانی پیدا نشد" else null
                        )
                    }
                    if (!manifest.maintenance) testLatencies()
                },
                onFailure = { e -> _ui.update { it.copy(loading = false, refreshing = false, error = e.message ?: "خطا در دریافت سرورها") } }
            )
        }
    }

    fun select(server: VpnServer) {
        manuallySelected = true
        _ui.update { it.copy(selectedServerId = server.id) }
    }

    private fun testLatencies() {
        viewModelScope.launch {
            val servers = _ui.value.servers
            val semaphore = Semaphore(6)
            val results = servers.map { server ->
                async {
                    semaphore.withPermit { server.id to LatencyTester.test(server) }
                }
            }.awaitAll().toMap()
            _ui.update { state ->
                val best = results.filterValues { it != null }.minByOrNull { it.value ?: Int.MAX_VALUE }?.key
                state.copy(
                    latencies = results,
                    selectedServerId = if (!manuallySelected && best != null) best else state.selectedServerId
                )
            }
        }
    }
}
