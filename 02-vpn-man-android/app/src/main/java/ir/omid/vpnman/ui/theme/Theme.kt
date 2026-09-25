package ir.omid.vpnman.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import ir.omid.vpnman.R

private val Vazir = FontFamily(
    Font(R.font.vazirmatn_regular, FontWeight.Normal),
    Font(R.font.vazirmatn_medium, FontWeight.Medium),
    Font(R.font.vazirmatn_bold, FontWeight.Bold)
)

private val Colors = darkColorScheme(
    primary = androidx.compose.ui.graphics.Color(0xFF7DE3C3),
    onPrimary = androidx.compose.ui.graphics.Color(0xFF07110E),
    secondary = androidx.compose.ui.graphics.Color(0xFF8FA7FF),
    background = androidx.compose.ui.graphics.Color(0xFF080B14),
    surface = androidx.compose.ui.graphics.Color(0xFF101725),
    surfaceVariant = androidx.compose.ui.graphics.Color(0xFF151E2F),
    onBackground = androidx.compose.ui.graphics.Color(0xFFF3F7FB),
    onSurface = androidx.compose.ui.graphics.Color(0xFFF3F7FB),
    onSurfaceVariant = androidx.compose.ui.graphics.Color(0xFFA9B4C4),
    error = androidx.compose.ui.graphics.Color(0xFFFF7E86)
)

private val AppTypography = Typography(
    displaySmall = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Bold, fontSize = 30.sp),
    headlineSmall = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Bold, fontSize = 22.sp),
    titleLarge = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Bold, fontSize = 19.sp),
    titleMedium = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Medium, fontSize = 16.sp),
    bodyLarge = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Normal, fontSize = 15.sp),
    bodyMedium = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Normal, fontSize = 13.sp),
    labelLarge = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Medium, fontSize = 14.sp),
    labelMedium = TextStyle(fontFamily = Vazir, fontWeight = FontWeight.Medium, fontSize = 12.sp)
)

@Composable
fun VpnManTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = Colors, typography = AppTypography, content = content)
}
